// Call Flow runtime — walks a CALL flow graph turn-by-turn during a live
// WABA voice call. (ESM — node/ is "type":"module".)
//
// It does NOT touch audio. It reuses the bridge's existing primitives via
// an injected `deps` object: speak(text) for TTS, aiReply(node) for an LLM
// turn (through Laravel /api/flow-node/ai-call, with optional knowledge
// base), webSearch(query) for the Search node, and endCall() to hang up.
//
// A flow of Trigger → AI Respond (loop) → Hang-up reproduces today's single
// assistant behaviour exactly; richer flows branch / search / transfer.
//
// Graph shape = the raw builder JSON: { flowNodes:[{id,type,data}],
// flowEdges:[{source,target,sourceHandle}] }. Node types are the builder
// ids (cf_say / cf_listen / cf_ai / cf_menu / cf_search / cf_transfer /
// cf_hangup, plus trigger). Ports: 'out' / 'p0..pN' / 'nomatch'.

const MAX_STEPS = 40; // hard stop against accidental loops within one turn

function nodeData(n) { return n && (n.data || n) || {}; }

function subst(str, vars) {
  return String(str || '').replace(/\{\{\s*([\w.]+)\s*\}\}/g, (_, k) => {
    const v = vars[k];
    return (v === undefined || v === null) ? '' : (typeof v === 'object' ? JSON.stringify(v) : String(v));
  });
}

/** Build quick lookups from the flow graph. */
function indexFlow(flow) {
  const nodes = {};
  (flow.flowNodes || []).forEach((n) => { if (n && n.id) nodes[n.id] = n; });
  return { nodes, edges: (flow.flowEdges || []) };
}

/** Resolve the node reached by leaving `nodeId` via `port` (default 'out'). */
function nextNode(state, nodeId, port = 'out') {
  const e = state.edges.find((x) =>
    x.source === nodeId && (
      port == null ||
      x.sourceHandle === port ||
      (port === 'out' && (!x.sourceHandle || x.sourceHandle === 'out'))
    ));
  return e ? state.nodes[e.target] : null;
}

/** The first node after the trigger (or the trigger's out-edge target). */
function entryNode(state) {
  const trig = Object.values(state.nodes).find((n) => (n.type === 'trigger'));
  if (trig) return nextNode(state, trig.id, 'out') || null;
  // No trigger? start at the first non-trigger node.
  return Object.values(state.nodes)[0] || null;
}

/** Attach a parsed call flow to the bridge session. */
export function attachCallFlow(s, flowJson) {
  if (!flowJson || !Array.isArray(flowJson.flowNodes) || flowJson.flowNodes.length === 0) return false;
  const idx = indexFlow(flowJson);
  s.callFlow = {
    nodes: idx.nodes,
    edges: idx.edges,
    vars: {},
    waitingNodeId: null, // the cf_listen we're parked on
    ended: false,
  };
  return true;
}

export function hasCallFlow(s) { return !!(s && s.callFlow && !s.callFlow.ended); }

/**
 * Walk forward from `node`, executing voice actions, until we hit a
 * cf_listen (park and wait for the caller) or end the call. Returns when
 * control should go back to the caller (or the call is over).
 */
async function walk(s, node, deps) {
  const cf = s.callFlow;
  let steps = 0;
  while (node && steps++ < MAX_STEPS && !cf.ended) {
    const type = node.type;
    const d = nodeData(node);

    if (type === 'cf_listen') {
      cf.waitingNodeId = node.id;            // park here; bridge STT resumes
      return;
    }

    if (type === 'cf_say') {
      await deps.speak(subst(d.text, cf.vars));
      node = nextNode(cf,node.id, 'out');
      continue;
    }

    if (type === 'cf_ai') {
      const reply = await deps.aiReply({
        model: d.model || 'gpt-4o-mini',
        prompt: subst(d.prompt, cf.vars),
        assistantId: parseInt(d.assistant || d.assistant_id || 0, 10) || 0,
      });
      if (reply) { cf.vars[d.save || 'ai_reply'] = reply; await deps.speak(reply); }
      // End the call if the caller said goodbye and the node opts in.
      if ((d.endOnGoodbye ?? true) && deps.callerSaidGoodbye && deps.callerSaidGoodbye()) {
        return endCall(s, deps, '');
      }
      node = nextNode(cf,node.id, 'out');
      continue;
    }

    if (type === 'cf_assistant') {
      // Hand this turn to a specific VOICE assistant (AiCallAssistant): its own
      // persona + model answer, spoken back. Runs inside the turn-based flow —
      // pair with a cf_listen before it to make it conversational.
      const reply = await deps.aiReply({
        model: d.model || 'gpt-4o-mini',
        prompt: subst(d.prompt || '', cf.vars),
        callAssistantId: parseInt(d.assistant || d.call_assistant_id || 0, 10) || 0,
      });
      if (reply) { cf.vars[d.save || 'assistant_reply'] = reply; await deps.speak(reply); }
      if ((d.endOnGoodbye ?? true) && deps.callerSaidGoodbye && deps.callerSaidGoodbye()) {
        return endCall(s, deps, '');
      }
      node = nextNode(cf, node.id, 'out');
      continue;
    }

    if (type === 'cf_search') {
      const filler = subst(d.filler, cf.vars);
      if (filler) await deps.speak(filler);            // no dead air
      const results = await deps.webSearch(subst(d.query, cf.vars));
      cf.vars[d.save || 'search'] = results || '';
      node = nextNode(cf,node.id, 'out');
      continue;
    }

    if (type === 'cf_menu') {
      const port = classifyMenu(d, cf.vars);
      node = nextNode(cf,node.id, port) || nextNode(cf,node.id, 'nomatch');
      continue;
    }

    if (type === 'cf_transfer') {
      if (d.message) await deps.speak(subst(d.message, cf.vars));
      const ok = deps.transfer ? await deps.transfer(subst(d.number, cf.vars)) : false;
      if (ok) { cf.ended = true; return; }
      node = nextNode(cf,node.id, 'out');  // "if no answer" path
      continue;
    }

    if (type === 'cf_hangup') {
      return endCall(s, deps, subst(d.goodbye, cf.vars));
    }

    // Unknown node → just advance so the flow never dead-ends.
    node = nextNode(cf,node.id, 'out');
  }
  if (!node && !cf.ended) {
    // Ran off the end of the graph — end gracefully.
    return endCall(s, deps, '');
  }
}

async function endCall(s, deps, goodbye) {
  s.callFlow.ended = true;
  if (goodbye) await deps.speak(goodbye);
  if (deps.endCall) await deps.endCall();
}

/** Decide which menu branch a caller turn maps to → returns a port id. */
function classifyMenu(d, vars) {
  const opts = Array.isArray(d.options) ? d.options : [];
  const said = String(vars.caller_said || vars.__lastTurn || '').toLowerCase();
  for (let i = 0; i < opts.length; i++) {
    const m = String(opts[i].match || '').toLowerCase().trim();
    if (!m) continue;
    // digit / keyword / intent all reduce to substring containment for v1.
    if (said.includes(m)) return 'p' + i;
  }
  return 'nomatch';
}

/** Call start: run from the entry node until the first Listen (or end). */
export async function runFromStart(s, deps) {
  if (!hasCallFlow(s)) return;
  await walk(s, entryNode(s.callFlow), deps);
}

/** Caller just spoke `text`: store it, advance from the parked Listen. */
export async function handleTurn(s, text, deps) {
  if (!hasCallFlow(s)) return false;
  const cf = s.callFlow;
  cf.vars.__lastTurn = text;
  const parked = cf.waitingNodeId ? cf.nodes[cf.waitingNodeId] : null;
  if (parked) {
    const d = nodeData(parked);
    cf.vars[d.save || d.variable || 'caller_said'] = text;
    cf.waitingNodeId = null;
    await walk(s, nextNode(cf,parked.id, 'out'), deps);
  } else {
    // Not parked on a Listen (e.g. AI-loop flow) — re-enter at entry.
    cf.vars.caller_said = text;
    await walk(s, entryNode(s.callFlow), deps);
  }
  return true;
}

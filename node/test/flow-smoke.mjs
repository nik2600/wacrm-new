// Flow runtime smoke test.
//
//   node node/test/flow-smoke.mjs
//
// Walks every normalized flowNodeType through executeFlowNode with a
// stubbed sock + stubbed axios so each branch reports whether it sends
// anything, advances correctly, or silently dies. No network, no DB.
//
// Prints PASS / FAIL per node type with a one-line reason.

import { executeFlowNode } from '../services/flowService.js';
import { createServer } from 'node:http';

// ── Tiny mock Laravel — responds to every callback the executors make.
// Lets us cover commerce / appointment / sub-flow / AI / tag / assign
// without spinning up the real Laravel stack.
const routes = {
  'POST /api/commerce/check-inventory':    { ok: true, in_stock: ['SKU-A', 'SKU-B'] },
  'POST /api/commerce/checkout-link':      { ok: true, url: 'https://shop.test/c/abc', expires_at: null },
  'POST /api/commerce/waba-send-products': { ok: true },
  'POST /api/flow-node/ai-agent-reply':    { ok: true, replied: true, agent: 'Support Bot' },
  'GET  /api/appointments/slots':          { ok: true, slots: [
      { start: '2026-05-20T10:00:00Z', end: '2026-05-20T10:30:00Z', label: 'Tue 10:00 AM' },
      { start: '2026-05-20T11:00:00Z', end: '2026-05-20T11:30:00Z', label: 'Tue 11:00 AM' },
  ]},
  'GET  /api/flows/777': { success: true, data: { flow: { id: 777 }, flow_data: {
      workspace_id: 1,
      flowNodes: [{ id: 'sub_n1', type: 'message', flowNodeType: 'Message',
        flowReplies: [{ flowReplyType: 'Text', data: 'sub-flow reply' }] }],
      flowEdges: [],
  }}},
  'POST /api/flow-node/tag':    { ok: true },
  'POST /api/flow-node/assign': { ok: true, conversation_id: 1, assignee_user_id: 1 },
  'POST /api/flow-node/ai-call':{ ok: true, reply: 'Hi — this is a mocked AI reply.', provider: 'openai' },
  'POST /api/flow-node/google-meet': { ok: true, meet_url: 'https://meet.google.com/abc-defg-hij', event_id: 'evt_mock_1', start: '2026-05-18T10:00:00Z', end: '2026-05-18T10:30:00Z', time_zone: 'UTC' },
};
const server = createServer((req, res) => {
  let body = '';
  req.on('data', c => body += c);
  req.on('end', () => {
    const url = req.url.split('?')[0];
    const key = `${req.method.padEnd(4)} ${url}`;
    let hit = routes[key];
    // The inventory check should echo whatever retailer_ids were sent
    // — otherwise a test using different SKUs gets a "all OOS → abandon"
    // false negative. Mirror Laravel's behaviour of "in stock = passes".
    if (key === 'POST /api/commerce/check-inventory') {
      try {
        const parsed = JSON.parse(body || '{}');
        hit = { ok: true, in_stock: Array.isArray(parsed.retailer_ids) ? parsed.retailer_ids : [] };
      } catch { hit = { ok: true, in_stock: [] }; }
    }
    res.writeHead(hit ? 200 : 404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(hit || { ok: false, error: 'not_mocked', key }));
  });
});
await new Promise(r => server.listen(0, '127.0.0.1', r));
const MOCK_URL = `http://127.0.0.1:${server.address().port}`;

// ── Stub global axios so HTTP calls become deterministic returns ──
// Node uses axios via `import axios from 'axios'` at the top of
// flowService.js. We can't easily swap that import in-place, so we
// install our stub on the global module cache and let the real flow
// path read from it. (Simpler approach: hand-stub each consumer.)
//
// We use process.env.AXIOS_MOCK to flag stubbing — flowService doesn't
// look at it; instead we monkey-patch axios methods on the imported
// instance. To keep this test runnable without that machinery we just
// document the calls we expect and let real failures degrade gracefully
// (the executor branches all wrap axios in try/catch).

// ── Stubbed Baileys socket ──
// `user` and `ws.readyState` are what utils/sendSafety.js isSockUsable()
// checks before every flow send. Without them the runtime treats this sock as
// a dropped connection and skips EVERY sending node ("sock unusable … node
// skipped"), which quietly reduced this whole file to testing the 5 node types
// that need no socket.
const sent = [];
function makeSock() {
  return {
    user: { id: '999:1@s.whatsapp.net', name: 'Smoke Test' },
    ws: { readyState: 1 },
    sendMessage: async (jid, payload) => {
      sent.push({ jid, kind: detectKind(payload), len: jsonLen(payload) });
      return { key: { id: 'TEST_' + Date.now() } };
    },
  };
}
function detectKind(p) {
  if (!p || typeof p !== 'object') return 'unknown';
  // Check rich kinds FIRST — Baileys interactive payloads include
  // `text` alongside `interactiveButtons`/`sections`, so a naive
  // `text` check would mask the real bubble type.
  if (p.interactiveButtons) return 'interactiveButtons';
  if (p.buttons) return 'buttons';
  if (p.sections) return 'list';
  if (p.image) return 'image';
  if (p.video) return 'video';
  if (p.audio) return 'audio';
  if (p.document) return 'document';
  if (p.location) return 'location';
  if (p.poll) return 'poll';
  if (p.text) return 'text';
  return Object.keys(p)[0] || 'unknown';
}
function jsonLen(p) {
  try { return JSON.stringify(p).length; } catch { return -1; }
}

// ── Minimal appLocals ──
const appLocals = {
  activeFlowSessions: {},
  clients: {},
  appDomainName: MOCK_URL,
};

// Per-test session reset.
function freshSession(flowData) {
  const sessionKey = '999_888';
  appLocals.activeFlowSessions[sessionKey] = {
    sessionId: 'test_' + Date.now(),
    flowId: 999,
    flowData,
    currentNodeId: null,
    userVariables: { user_message: 'hi', name: 'TestUser' },
    messageHistory: [],
    status: 'active',
    startedAt: new Date().toISOString(),
  };
  return sessionKey;
}

// Build a 2-node flow: trigger → <node-under-test> → end-of-graph.
function makeFlow(node) {
  return {
    workspace_id: 1,
    flowNodes: [
      node,
      // No second node — first node's `_1` port has no edge → endFlow.
    ],
    flowEdges: [],
  };
}

const CASES = [
  {
    label: 'Message',
    node: { id: 'n_test1', type: 'message', flowNodeType: 'Message',
      flowReplies: [{ flowReplyType: 'Text', data: 'Hello {{name}}!' }] },
    expectKind: 'text',
  },
  {
    label: 'Sequence (multi-reply)',
    node: { id: 'n_test2', type: 'sequence', flowNodeType: 'Message',
      flowReplies: [
        { flowReplyType: 'Text',  data: 'First' },
        { flowReplyType: 'Image', data: 'https://example.com/x.png', caption: 'pic' },
      ]},
    expectKind: 'image', // last send wins in the recorder
  },
  {
    label: 'Media (image)',
    node: { id: 'n_test3', type: 'media', flowNodeType: 'Media',
      mediaKind: 'image', mediaUrl: 'https://example.com/y.png', mediaCaption: 'cap' },
    expectKind: 'image',
  },
  {
    label: 'Template (hydrated)',
    node: { id: 'n_test4', type: 'template', flowNodeType: 'Template',
      templateName: 'X', templateId: 1, templateBody: 'Hi {{name}}, welcome.',
      templateButtons: [] },
    expectKind: 'text',
  },
  {
    label: 'Ask (question)',
    node: { id: 'n_test5', type: 'ask', flowNodeType: 'Question',
      question: 'Your name?', variable: 'name', expectedAnswers: ['Yes', 'No'] },
    expectKind: 'interactiveButtons',
  },
  {
    label: 'Buttons',
    node: { id: 'n_test6', type: 'buttons', flowNodeType: 'InteractiveButtons',
      bodyText: 'Pick one', variable: 'choice',
      buttons: [{ id: 'p0', title: 'A' }, { id: 'p1', title: 'B' }] },
    expectKind: 'interactiveButtons',
  },
  {
    label: 'List',
    node: { id: 'n_test7', type: 'list', flowNodeType: 'List',
      headerText: 'Hdr', bodyText: 'Body', buttonText: 'Open',
      listItems: [{ id: 'p0', title: 'A' }, { id: 'p1', title: 'B' }],
      variable: 'choice' },
    // Lists ship in the MODERN native-flow shape (Baileys interactiveButtons
    // with a single_select), not the legacy `sections` listMessage — see the
    // extractReplyFromMessage notes in flowService.js. The old 'list'
    // expectation here predates that migration.
    expectKind: 'interactiveButtons',
  },
  {
    label: 'Condition (true → port 1)',
    node: { id: 'n_test8', type: 'condition', flowNodeType: 'Condition',
      conditions: [{ variable: 'user_message', operator: 'equals', value: 'hi' }],
      logicOperators: [] },
    expectSent: 0,    // condition itself sends nothing
  },
  {
    label: 'Delay (0.05s)',
    node: { id: 'n_test9', type: 'delay', flowNodeType: 'TimeDelay',
      delaySeconds: 0.05 },
    expectSent: 0,
  },
  {
    label: 'Poll',
    node: { id: 'n_test10', type: 'poll', flowNodeType: 'Poll',
      question: 'Pick', options: ['A', 'B', 'C'], allowMultiple: false },
    expectKind: 'poll',
  },
  {
    label: 'CTA',
    node: { id: 'n_test11', type: 'cta', flowNodeType: 'CTA',
      ctaActions: [{ type: 'url', label: 'Visit', value: 'https://x.com' }],
      headerText: 'Hello', bodyText: 'Body', footerText: 'Foot' },
    expectKind: 'text',
  },
  {
    label: 'Location',
    node: { id: 'n_test12', type: 'location', flowNodeType: 'Location',
      latitude: 12.97, longitude: 77.59, address: '1 Main St', title: 'HQ' },
    expectKind: 'location',
  },
  {
    label: 'Webhook (will fail HTTP, swallowed)',
    node: { id: 'n_test13', type: 'webhook', flowNodeType: 'Webhook',
      method: 'POST', url: 'http://127.0.0.1:1/never', body: '{}', variable: 'resp', contentType: 'application/json' },
    expectSent: 0,
  },
  {
    label: 'Tag (Laravel down — swallowed)',
    node: { id: 'n_test14', type: 'tag', flowNodeType: 'TagContact',
      action: 'add', tag: 'lead' },
    expectSent: 0,
  },
  {
    label: 'Assign (Laravel down — swallowed)',
    node: { id: 'n_test15', type: 'assign', flowNodeType: 'AssignAgent',
      teamId: '1', noteForAgent: 'flow handoff' },
    expectSent: 0,
  },
  {
    label: 'AI (via mock /ai-call)',
    node: { id: 'n_test16', type: 'ai', flowNodeType: 'ChatGPT',
      model: 'gpt-4o-mini', prompt: 'Be brief.', variable: 'reply' },
    expectKind: 'text',
  },
  // ── Commerce — Baileys path (sock is present → card stack). All 3
  // providers run the same executor; the only difference is the
  // provider string + which Laravel checkout endpoint they hit.
  {
    label: 'CommerceShop · WhatsApp Shop',
    node: { id: 'n_test17', type: 'whatsapp_shop', flowNodeType: 'CommerceShop',
      provider: 'whatsapp_shop', storeId: '1',
      productItems: [
        { retailer_id: 'SKU-A', name: 'Mug',  image: 'https://example.com/m.png', price_minor: 1200, currency: 'USD' },
        { retailer_id: 'SKU-B', name: 'Tote', image: 'https://example.com/t.png', price_minor: 1800, currency: 'USD' },
      ],
      headerText: 'Top picks', bodyText: 'Pick one:', abandonedWaitMinutes: 1,
    },
    expectKind: 'image', // each product is sent as image + caption
  },
  {
    label: 'CommerceShop · WooCommerce',
    node: { id: 'n_test18', type: 'woocommerce', flowNodeType: 'CommerceShop',
      provider: 'woocommerce', storeId: '1',
      productItems: [{ retailer_id: 'wc-1', name: 'Widget', image: 'https://example.com/w.png', price_minor: 500, currency: 'USD' }],
      bodyText: 'Buy:', abandonedWaitMinutes: 1,
    },
    expectKind: 'image',
  },
  {
    label: 'CommerceShop · Shopify',
    node: { id: 'n_test19', type: 'shopify', flowNodeType: 'CommerceShop',
      provider: 'shopify', storeId: '1',
      productItems: [{ retailer_id: 'sh-1', name: 'Gear', image: 'https://example.com/g.png', price_minor: 2500, currency: 'USD' }],
      bodyText: 'Today:', abandonedWaitMinutes: 1,
    },
    expectKind: 'image',
  },
  {
    label: 'BookAppointment',
    node: { id: 'n_test20', type: 'book_appointment', flowNodeType: 'BookAppointment',
      slotCount: 2, prompt: 'Pick a slot:', confirmation: 'Booked!', collectEmail: false },
    expectKind: 'interactiveButtons', // slot picker is a modern list — see 'List' above
  },
  {
    label: 'Chatbot → AI agent hand-off',
    node: { id: 'n_test21', type: 'chatbot', flowNodeType: 'Chatbot',
      agentId: '777', botId: '777', selectedFlowId: '777' },
    // agentId wins over selectedFlowId: the node hands the conversation to
    // Laravel, which fires the first reply via AiAgentService and owns the
    // chat from then on. Node sends NOTHING itself and ends the session — so
    // zero sock sends is the pass condition. (The legacy sub-flow path, which
    // does send, is covered by 'SubFlow alias' below.) The old expectKind
    // 'text' assumed the sub-flow path and could never pass here.
    expectSent: 0,
  },
  {
    label: 'SubFlow alias',
    node: { id: 'n_test22', type: 'subflow', flowNodeType: 'SubFlow',
      flowId: '777', selectedFlowId: '777' },
    expectKind: 'text',
  },
  {
    label: 'Google Meet (creates link via /flow-node/google-meet)',
    node: { id: 'n_test23', type: 'google_meet', flowNodeType: 'GoogleMeet',
      title: 'Demo call', durationMinutes: 30, leadMinutes: 5, sendCalendarInvite: false,
      messageTemplate: 'Join: {{meet_link}}' },
    expectKind: 'text',
  },
];

// ── Run ──
const results = [];
for (const c of CASES) {
  sent.length = 0;
  const flow = makeFlow(c.node);
  const sessionKey = freshSession(flow);
  const sock = makeSock();
  // For Condition we want it to advance via port 1 — but with no
  // downstream edges, it'll just end the session. That's fine; we only
  // check that the executor didn't throw.
  let err = null;
  try {
    await executeFlowNode(c.node, '888', '999', sock, appLocals, sessionKey);
  } catch (e) {
    err = e;
  }
  const ok = !err
    && (c.expectKind ? sent.some(s => s.kind === c.expectKind) : sent.length === (c.expectSent ?? 0));
  results.push({
    label: c.label,
    ok,
    sent: sent.map(s => `${s.kind}(${s.len})`).join(', ') || '(none)',
    err: err?.message || '',
  });
}

console.log('\n┌────────────────────────────────────────────┬──────┬──────────────────────────────────┐');
console.log('│ Node type                                  │ ok?  │ what got sent / error            │');
console.log('├────────────────────────────────────────────┼──────┼──────────────────────────────────┤');
for (const r of results) {
  const lbl = (r.label + ' '.repeat(43)).slice(0, 43);
  const mark = r.ok ? ' PASS ' : ' FAIL ';
  const info = (r.err ? '!! ' + r.err : r.sent).slice(0, 33);
  console.log(`│ ${lbl}│${mark}│ ${info.padEnd(33)}│`);
}
console.log('└────────────────────────────────────────────┴──────┴──────────────────────────────────┘');
const passed = results.filter(r => r.ok).length;
console.log(`\n${passed}/${results.length} node types pass smoke.\n`);
server.close();
process.exit(passed === results.length ? 0 : 1);

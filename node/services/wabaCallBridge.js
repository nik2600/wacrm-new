// services/wabaCallBridge.js
// ============================================================
// WABA Calling — AI voice pickup, real-time audio loop end-to-end.
//
// Required deps (already in package.json):
//   - @roamhq/wrtc    WebRTC peer connection + audio sink/source
//                     (Maintained fork of the abandoned original `wrtc`
//                     package — same API. Required for Node 18+.)
//   - ws              WebSocket clients for STT and TTS
//
// Pipeline per call:
//
//   Meta webhook `connect`
//        ↓ (Laravel calls /api/waba-call/answer with sdp_offer)
//   openSession()
//        ↓ Build RTCPeerConnection
//        ↓ setRemoteDescription(offer)
//        ↓ Attach RTCAudioSource (outbound to caller)
//        ↓ Attach RTCAudioSink to inbound track when it lands
//        ↓ createAnswer + setLocalDescription
//        ↓ patch a=setup:actpass → a=setup:active
//        ↓ POST pre_accept then accept to Graph /calls
//
//   Caller speaks
//        ↓ wrtc gives us PCM frames (Int16, mono, 48 kHz)
//        ↓ resample to 16 kHz mono → Deepgram WS STT
//        ↓ STT emits `is_final` transcripts → buffer turn
//        ↓ end-of-speech → call LLM with conversation history
//        ↓ LLM reply → ElevenLabs TTS streaming WS (PCM 16k output)
//        ↓ upsample to 48 kHz → push frames into RTCAudioSource
//        ↓ caller hears the AI
//
//   Recording
//        ↓ Tee both PCM streams to .raw files under
//          public/uploads/call-recordings/{call_id}_(agent|user).pcm
//        ↓ On terminate, ffmpeg can convert → wav/mp3 (or do it lazily
//          in Laravel when the operator clicks Play). For now the raw
//          PCM URL lands in ai_call_logs.recording_url_*.
//
//   On terminate
//        ↓ closeSession() flushes sockets, finalises recordings, posts
//          terminate to Graph /calls, writes a transcript_complete
//          event back to Laravel via /api/waba-call/transcript-turn.
// ============================================================

import axios from 'axios';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { WebSocket } from 'ws';
import { attachCallFlow, hasCallFlow, runFromStart, handleTurn } from './callFlowRuntime.js';

// @roamhq/wrtc is optional at module-load time so the rest of Node
// still boots when the dep isn't installed yet. The bridge gracefully
// falls back to "decline + voicemail" when wrtc is unavailable.
//
// Original `wrtc` (npm) was abandoned in 2023 and fails to build on Node
// 18+. We use @roamhq/wrtc — a community-maintained fork with the same
// exports — and dynamically import it so older installs still boot.
let RTCPeerConnection, RTCSessionDescription, RTCAudioSource, RTCAudioSink;
try {
  const wrtc = await import('@roamhq/wrtc');
  // The fork exposes the API as default for ESM and as named on CJS;
  // grab whichever shape we got.
  const m = wrtc.default || wrtc;
  RTCPeerConnection      = m.RTCPeerConnection;
  RTCSessionDescription  = m.RTCSessionDescription;
  const nm = m.nonstandard || {};
  RTCAudioSource = nm.RTCAudioSource;
  RTCAudioSink   = nm.RTCAudioSink;
  if (!RTCPeerConnection || !RTCAudioSource || !RTCAudioSink) {
    console.warn('[WABA-BRIDGE] @roamhq/wrtc loaded but expected exports missing — AI voice pickup disabled.');
    RTCPeerConnection = null;
  } else {
    console.log('[WABA-BRIDGE] @roamhq/wrtc loaded — AI voice pickup ready.');
  }
} catch (e) {
  console.warn('[WABA-BRIDGE] @roamhq/wrtc not installed (' + (e?.code || e?.message) + ') — AI voice pickup disabled. Run `npm install` in node/.');
}

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const RECORDING_DIR = path.join(__dirname, '../../public/uploads/call-recordings');
try { fs.mkdirSync(RECORDING_DIR, { recursive: true }); } catch {}

// STUN discovers the server's public IP; a TURN relay is what actually carries
// the media when the server is behind NAT / a firewall (the common cause of
// `connectionState=failed` → the AI talks but no audio reaches the caller).
// Server-side WebRTC to WhatsApp essentially REQUIRES TURN — set it in .env:
//   WABA_CALL_TURN_URL=turn:your-turn-host:3478   (or turns:...:5349)
//   WABA_CALL_TURN_USER=...   WABA_CALL_TURN_PASS=...
// Multiple TURN urls can be comma-separated in WABA_CALL_TURN_URL.
const ICE_SERVERS = (() => {
  const list = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun.relay.metered.ca:80' },
  ];
  const turnUrl  = (process.env.WABA_CALL_TURN_URL  || '').trim();
  const turnUser = (process.env.WABA_CALL_TURN_USER || '').trim();
  const turnPass = (process.env.WABA_CALL_TURN_PASS || '').trim();
  if (turnUrl) {
    list.push({
      urls: turnUrl.split(',').map((u) => u.trim()).filter(Boolean),
      username: turnUser,
      credential: turnPass,
    });
    console.log(`[WABA-BRIDGE] TURN relay configured (${turnUrl.split(',').length} url(s)) — media can traverse NAT/firewall.`);
  } else {
    console.warn('[WABA-BRIDGE] no TURN relay set (WABA_CALL_TURN_URL) — server-side WebRTC media will FAIL behind NAT/firewall (connectionState=failed). Configure a TURN server for AI calls to carry audio.');
  }
  return list;
})();

/**
 * Normalise an SDP blob to CRLF line endings. Meta's WhatsApp Calling SDP
 * arrives with bare LF (\n), but @roamhq/wrtc's parser (like Chrome's)
 * REQUIRES \r\n on every line plus a trailing terminator — otherwise
 * setRemoteDescription throws "Invalid SDP line". Fold everything to CRLF
 * and guarantee a trailing CRLF.
 */
function normalizeSdp(sdp) {
  if (!sdp) return sdp;
  let out = String(sdp).replace(/\r\n/g, '\n').replace(/\r/g, '\n'); // → all LF
  out = out.replace(/\n/g, '\r\n');                                  // → all CRLF
  if (!out.endsWith('\r\n')) out += '\r\n';
  return out;
}

// Module-scope session table keyed by meta_call_id. Each session is a
// self-contained per-call orchestration object.
const sessions = new Map();

/**
 * Open the caller-side PCM file. Split out of the ontrack handler so
 * recording can begin mid-call, the moment the operator asks for it.
 */
function openUserRecording(s) {
  if (s.recUser) return;                       // already recording
  s.recUser = fs.createWriteStream(path.join(RECORDING_DIR, `${s.metaCallId}_user.pcm`));
  console.log(`[WABA-BRIDGE] caller recording started call=${s.metaCallId}`);
}

/**
 * Arm recording for a live call — invoked when the operator presses
 * Record on the call popup.
 *
 * Recording used to begin automatically as soon as the media track
 * connected, so every call was captured whether or not anyone wanted it
 * and the customer was never told. Now nothing is written until this is
 * called, and only audio from THIS MOMENT ON lands in the file — the
 * earlier part of the conversation is not retroactively captured,
 * because the stream simply did not exist yet.
 *
 * Returns false when the call is unknown (already ended / bad id) so the
 * caller can report that instead of silently doing nothing.
 */
export function startRecording(metaCallId) {
  const s = sessions.get(metaCallId);
  if (!s) return false;
  if (s.recordingArmed) return true;           // idempotent — double-click safe
  s.recordingArmed = true;
  s.recordingStartedAt = Date.now();
  // The caller's track is usually already live, so open its file now.
  // The agent side opens on its next utterance (see speak()).
  if (s.audioSink && s.assistantConfig?.record_user) openUserRecording(s);
  console.log(`[WABA-BRIDGE] recording ARMED by operator call=${metaCallId}`);
  return true;
}

/**
 * Open (or re-attach to) a session for an incoming WABA call.
 * Idempotent — duplicate webhook delivery won't create two bridges.
 */
export function openSession(app, args) {
  if (sessions.has(args.metaCallId)) return sessions.get(args.metaCallId);

  if (!RTCPeerConnection) {
    console.warn(`[WABA-BRIDGE] cannot open — wrtc missing. call=${args.metaCallId}`);
    return null;
  }

  const session = {
    ...args,
    startedAt: Date.now(),
    closed: false,
    appDomain: app.locals.appDomainName,
    // Every credential flows through from Laravel per session —
    // sourced from system_settings + wa_provider_configs.credentials_json.
    // No env fallback so a partial admin setup fails loud instead of
    // accidentally using stale/dev keys.
    nodeToken:     args.nodeToken     || '',
    metaToken:     args.metaToken     || '',
    graphVersion:  args.graphVersion  || 'v23.0',
    phoneNumberId: args.phoneNumberId || '',

    pc: null,
    audioSource: null,            // outbound (AI → caller)
    audioSink: null,              // inbound  (caller → AI)
    sttSocket: null,
    ttsSocket: null,
    assistantConfig: null,

    // Recording — write raw 48k mono PCM to disk; Laravel converts to
    // wav/mp3 on first playback request.
    recAgent: null,
    recUser: null,

    // Transcript buffer for the live conversation
    transcript: [],
    pendingUserTurn: '',
    lastUserTurnAt: 0,
  };
  sessions.set(args.metaCallId, session);

  console.log(`[WABA-BRIDGE] opening call=${args.metaCallId} assistant=${args.assistantId} caller=${args.callerPhone}`);

  start(session).catch(e => {
    console.error(`[WABA-BRIDGE] start failed: ${e?.message}`);
    console.error(e?.stack);
    // Report the crash to Laravel so wa_calls.status flips to 'failed'
    // instead of staying stuck on 'ringing'/'connecting'. Without this
    // the operator sees a phantom active call in /call-logs.
    reportBridgeError(session, e?.message || 'start_failed').catch(() => {});
    closeSession(app, args.metaCallId);
  });

  return session;
}

/**
 * Tell Laravel that the bridge died so the call row doesn't stay
 * stuck. Best-effort — if Laravel is also down we just log and exit.
 */
async function reportBridgeError(s, reason) {
  try {
    await axios.post(
      `${s.appDomain}/api/waba-call/bridge-error`,
      { wa_call_id: s.waCallId, meta_call_id: s.metaCallId, reason: String(reason).slice(0, 500) },
      { timeout: 5000, headers: { 'X-Node-Token': s.nodeToken } },
    );
  } catch (e) {
    console.warn(`[WABA-BRIDGE] bridge-error report failed: ${e?.message}`);
  }
}

async function start(s) {
  // 1. Pull the assistant config + workspace-scoped voice keys from
  //    Laravel. Keys travel through AiKeyResolver (workspace BYOK →
  //    admin → env) so Node doesn't need them in its own env on
  //    multi-tenant installs.
  const [cfgRes, keysRes] = await Promise.all([
    axios.get(
      // workspace_id is REQUIRED by the handler (it workspace-scopes the
      // lookup) — without it Laravel returns HTTP 400 and this un-caught
      // request rejects the whole Promise.all, tearing the call down before
      // the AI can accept. s.workspaceId is set from the /answer payload.
      `${s.appDomain}/api/waba-call/assistant/${s.assistantId}?workspace_id=${s.workspaceId}`,
      { timeout: 8000, headers: { 'X-Node-Token': s.nodeToken } },
    ),
    axios.get(
      `${s.appDomain}/api/waba-call/voice-keys?workspace_id=${s.workspaceId}`,
      { timeout: 8000, headers: { 'X-Node-Token': s.nodeToken } },
    ).catch(e => ({ data: { ok: false, error: e?.message } })),
  ]);
  if (!cfgRes.data?.ok) {
    console.warn(`[WABA-BRIDGE] assistant fetch failed call=${s.metaCallId}`);
    await metaAction(s, 'reject');
    await reportBridgeError(s, 'assistant_fetch_failed');
    closeSession(null, s.metaCallId);
    return;
  }
  s.assistantConfig = cfgRes.data.assistant;
  // Voice keys resolved server-side via AiKeyResolver (workspace BYOK
  // → admin api_keys row). No env fallback — admin configures keys at
  // /admin/api-keys.
  s.deepgramKey   = (keysRes.data?.deepgram   || '').toString();
  s.openaiKey     = (keysRes.data?.openai     || '').toString();
  s.elevenlabsKey = (keysRes.data?.elevenlabs || '').toString();
  // STT provider: prefer Deepgram (lowest latency); otherwise use OpenAI's
  // realtime transcription so a workspace with only an OpenAI key still works.
  s.sttProvider = s.deepgramKey ? 'deepgram' : (s.openaiKey ? 'openai' : '');

  // Key trace — masked (first3…last3 + length), never the raw key. Lets us
  // confirm from the logs WHICH key reached Node and match it to the Laravel
  // [WABA-CALL][voice-keys] line, so "why is ElevenLabs invalid" is one glance:
  // if the masks differ, the wrong key was saved; if they match, the key itself
  // is bad on the vendor side.
  const _mask = (k) => (k && k.length) ? `${k.slice(0, 3)}…${k.slice(-3)} len${k.length}` : 'ABSENT';
  console.log(`[WABA-BRIDGE] keys ws=${s.workspaceId} deepgram=${_mask(s.deepgramKey)} openai=${_mask(s.openaiKey)} elevenlabs=${_mask(s.elevenlabsKey)} → STT=${s.sttProvider || 'NONE'} TTS=${s.elevenlabsKey ? 'elevenlabs' : 'none'} model=${s.assistantConfig?.model || '?'} langs=${JSON.stringify(s.assistantConfig?.meta?.languages || [])} call=${s.metaCallId}`);

  // Fail-fast guard. Without an STT key (Deepgram OR OpenAI) + a TTS key
  // (ElevenLabs) the AI bridge would accept the call and then sit silent —
  // the caller dials, hears the click, then nothing. Worse than declining.
  // Reject up-front so the AI voicemail fallback (AiFallback::trigger via
  // the terminating timer) can handle the call by sending a voice-note
  // "sorry we missed you" over chat instead.
  if (!s.sttProvider || !s.elevenlabsKey) {
    const missing = [];
    if (!s.sttProvider) missing.push('Deepgram or OpenAI (speech-to-text)');
    if (!s.elevenlabsKey) missing.push('ElevenLabs (voice)');
    console.warn(`[WABA-BRIDGE] missing ${missing.join(' + ')} key for ws=${s.workspaceId} — rejecting call so voicemail fallback fires. Configure at /admin/api-keys.`);
    await metaAction(s, 'reject');
    await reportBridgeError(s, `missing_voice_keys:${missing.join(',')}`);
    closeSession(null, s.metaCallId);
    return;
  }
  console.log(`[WABA-BRIDGE] hydrated "${s.assistantConfig.name}" provider=${s.assistantConfig.ai_provider} model=${s.assistantConfig.ai_model}`);

  // 2. WebRTC peer
  s.pc = new RTCPeerConnection({ iceServers: ICE_SERVERS });

  // ICE diagnostics — the ONLY way to see why media fails. Candidate `typ`
  // tells the story: only `host` (private IP) = no public path (need TURN);
  // `srflx` = STUN found the public IP; `relay` = TURN is carrying media.
  // A `connectionState=failed` with no `relay` candidate ⇒ add a TURN server.
  s.pc.onicecandidate = (e) => {
    if (!e.candidate) { console.log(`[WABA-BRIDGE] ICE gathering complete call=${s.metaCallId}`); return; }
    const m = /typ (\w+)/.exec(e.candidate.candidate || '');
    console.log(`[WABA-BRIDGE] ICE candidate typ=${m ? m[1] : '?'} call=${s.metaCallId}`);
  };
  s.pc.oniceconnectionstatechange = () => {
    console.log(`[WABA-BRIDGE] iceConnectionState=${s.pc.iceConnectionState} call=${s.metaCallId}`);
  };
  s.pc.onconnectionstatechange = () => {
    console.log(`[WABA-BRIDGE] connectionState=${s.pc.connectionState} call=${s.metaCallId}`);
  };

  // 3. Outbound audio source — the AI's TTS frames go here.
  s.audioSource = new RTCAudioSource();
  const aiTrack = s.audioSource.createTrack();
  s.pc.addTrack(aiTrack);

  // 4. When the caller's track arrives, plug an AudioSink to capture
  //    their PCM frames for STT + recording. record_user is gated on
  //    the workspace plan (access_call_recording) AND the assistant's
  //    per-side toggle — see FlowNodeActionsController::wabaCallAssistant.
  s.pc.ontrack = (event) => {
    if (event.track.kind !== 'audio') return;
    // Guard: ontrack can fire more than once (renegotiation / extra m-lines).
    // Each extra RTCAudioSink ALSO writes to recUser, so the caller recording
    // ends up 2-3x too long and garbled (overlapping writes) — bind exactly
    // once and ignore any duplicate track.
    if (s.audioSink) {
      console.log(`[WABA-BRIDGE] ignoring duplicate caller audio track call=${s.metaCallId}`);
      return;
    }
    console.log(`[WABA-BRIDGE] caller audio track received call=${s.metaCallId}`);
    s.audioSink = new RTCAudioSink(event.track);
    // Recording is NO LONGER started here. Opening the stream on track
    // arrival meant every WABA call was recorded the moment it connected,
    // with no operator consent and no way to decline. The operator now
    // presses Record on the call popup, which calls startRecording()
    // below. The sink still runs (STT needs it) — only the file write is
    // deferred, so nothing before the button press is captured.
    if (s.assistantConfig.record_user && s.recordingArmed) {
      openUserRecording(s);
    }
    s.audioSink.ondata = (frame) => {
      // frame.samples is Int16Array @ frame.sampleRate Hz. We ASSUME 48 kHz
      // mono downstream (STT config + the recording WAV header), so anything
      // else must be normalised or the recording plays at the wrong speed
      // (e.g. a 19 s call rendering as 6:57) and STT degrades.
      try {
        if (!s._callerFmtLogged) {
          s._callerFmtLogged = true;
          console.log(`[WABA-BRIDGE] caller audio fmt rate=${frame.sampleRate} ch=${frame.channelCount} nframes=${frame.numberOfFrames} call=${s.metaCallId}`);
        }
        // Mono PCM at the frame's REPORTED rate.
        let mono = Buffer.from(frame.samples.buffer, frame.samples.byteOffset, frame.samples.byteLength);
        if (frame.channelCount && frame.channelCount > 1) {
          mono = downmixToMono(mono, frame.channelCount);
        }

        // STT feed — keep EXACTLY the prior behaviour (it transcribes well):
        // resample by the frame's reported rate to 48 kHz.
        let buf = mono;
        if (frame.sampleRate && frame.sampleRate !== 48000) {
          buf = resampleLinear(mono, frame.sampleRate, 48000);
        }

        // Recording feed — REAL-TIME corrected. @roamhq/wrtc mislabels the
        // caller stream (reported 16000 Hz but actually delivers ~45k samples/s),
        // so the fixed 16->48k triple stretched the recorded voice ~2.8x
        // (309 s file + slow/garbled "no clear voice"). Measure the TRUE rate
        // from wall-clock and resample mono → a real 48 kHz so the recording
        // matches the call length and sounds natural.
        if (s.recUser) {
          const nMono = mono.byteLength >> 1;
          if (!s._sinkT0) { s._sinkT0 = Date.now(); s._sinkSamples = 0; }
          s._sinkSamples += nMono;
          const el = (Date.now() - s._sinkT0) / 1000;
          // Warm up on the reported rate briefly, then trust wall-clock.
          const effRate = el > 0.5
            ? Math.min(96000, Math.max(8000, Math.round(s._sinkSamples / el)))
            : (frame.sampleRate || 48000);
          s._effRate = effRate;
          const rec48 = (effRate === 48000) ? mono : resampleLinear(mono, effRate, 48000);
          s.recUser.write(rec48);
        }
        // Barge-in (OPT-IN, default OFF): let the caller talk over the AI to
        // stop it. Disabled by default because carrier lines with weak echo
        // cancellation echo the AI's own voice back loudly enough to trip it —
        // that cut every reply off after 2-3 words. Only enable per-assistant
        // (`barge_in: true`) on lines proven to have clean echo cancellation.
        if (s.speaking && (s.assistantConfig?.barge_in === true) && s.audioChunks && s.audioChunks.length) {
          const frameMs = (frame.numberOfFrames && frame.sampleRate)
            ? (frame.numberOfFrames / frame.sampleRate) * 1000 : 10;
          const rms = rmsInt16(buf);
          const talkingLongEnough = (Date.now() - (s._speakStartAt || 0)) >= BARGE_MIN_SPEAK_MS;
          if (rms >= BARGE_RMS_MIN && talkingLongEnough) {
            s._loudMs = (s._loudMs || 0) + frameMs;
            if (s._loudMs >= BARGE_SUSTAIN_MS) triggerBargeIn(s);
          } else {
            s._loudMs = 0;
          }
        }
        // Half-duplex: don't feed the caller's mic to the transcriber while
        // the AI is speaking — that audio is mostly the AI's own voice echoed
        // back by the phone, and transcribing it makes the AI reply to itself.
        // (A barge-in above flips s.speaking off, so the SAME frame — the
        // caller's interrupting words — starts feeding STT immediately.)
        if (s.sttSend && !s.speaking) s.sttSend(buf);
      } catch (e) {
        // Single dropped frame is fine — never crash the call.
      }
    };
  };

  // 5. Set remote description, mint answer, wait for ICE gathering to
  //    complete (so candidates are baked into the answer SDP — Meta
  //    doesn't support trickle ICE), then send pre_accept → accept.
  //    Per Meta spec the SDP in pre_accept and accept MUST match
  //    byte-for-byte or the call is rejected.
  // Meta's offer uses bare-LF line endings; wrtc's parser needs CRLF or it
  // throws "Invalid SDP line" (same quirk the browser peer hits).
  await s.pc.setRemoteDescription(new RTCSessionDescription({ type: 'offer', sdp: normalizeSdp(s.sdpOffer) }));
  const answer = await s.pc.createAnswer();
  await s.pc.setLocalDescription(answer);

  // Wait for ICE gathering — non-trickle. Hard 4s cap so a stalled STUN
  // server doesn't hang the call past Meta's 30-60s accept window.
  await waitForIceGatheringComplete(s.pc, 4000);

  // pc.localDescription has the full SDP including candidates after
  // gathering. Patch a=setup:actpass → a=setup:active (Meta sends
  // actpass, so we pick active).
  const localSdp = s.pc.localDescription?.sdp || answer.sdp;
  const finalSdp = localSdp.replace('a=setup:actpass', 'a=setup:active');
  s.answerSdp = finalSdp;  // snapshot so pre_accept + accept use the same bytes

  const preOk = await metaAction(s, 'pre_accept', finalSdp);
  if (!preOk) {
    console.warn(`[WABA-BRIDGE] pre_accept failed — aborting call=${s.metaCallId}`);
    closeSession(null, s.metaCallId);
    return;
  }
  // Meta requires ~1s gap between pre_accept and accept (per spec doc).
  await new Promise(r => setTimeout(r, 1000));
  const acceptOk = await metaAction(s, 'accept', finalSdp);
  if (!acceptOk) {
    // 131055 "Method not allowed" AFTER a successful pre_accept means Meta has
    // already moved this call to in-progress via the pre_accept (which carried
    // the SAME SDP answer) — so the follow-up accept is redundant, NOT a
    // failure. Treat it as connected instead of terminating a call that is
    // actually live. The old code hung up here, which is why AI calls died at
    // ~3s with 0 turns ("meta accept failed 131055 → aborting → terminate").
    const code = s._lastMetaError?.code;
    if (preOk && code === 131055) {
      console.log(`[WABA-BRIDGE] accept → 131055 (already in-progress after pre_accept) — treating as connected call=${s.metaCallId}`);
    } else {
      console.warn(`[WABA-BRIDGE] accept failed — aborting call=${s.metaCallId}`);
      closeSession(null, s.metaCallId);
      return;
    }
  }
  console.log(`[WABA-BRIDGE] call accepted, waiting for peer connection call=${s.metaCallId}`);

  // Tell Laravel the AI bridge has claimed the call so the AI-fallback
  // timer (scheduled in WaCallingWebhookController::handleConnect)
  // doesn't fire voicemail on top of a live AI conversation. The
  // /api/waba-call/bridge-accepted endpoint flips wa_calls.status from
  // 'ringing' → 'active' + handler_type='ai_agent' atomically.
  try {
    await axios.post(
      `${s.appDomain}/api/waba-call/bridge-accepted`,
      { wa_call_id: s.waCallId, meta_call_id: s.metaCallId, assistant_id: s.assistantId },
      { timeout: 5000, headers: { 'X-Node-Token': s.nodeToken } },
    );
  } catch (e) {
    console.warn(`[WABA-BRIDGE] bridge-accepted callback failed: ${e?.message}`);
  }

  // 5a. Wait for the WebRTC peer connection to actually come up before
  //     we push TTS. Per Meta's docs: "make sure to flow the media only
  //     after you receive 200 OK for your accept call. If the media
  //     flows too early, consumers will miss hearing the first few
  //     words." Hard 6s cap so we don't sit silent forever on bad
  //     networks — at that point we speak anyway and hope the path
  //     comes up mid-utterance.
  await waitForPeerConnected(s.pc, 6000);
  console.log(`[WABA-BRIDGE] peer connected call=${s.metaCallId} state=${s.pc.connectionState}`);
  // Start the 10 ms outbound audio clock now so RTP flows (silence until
  // the AI speaks) and the greeting plays at real-time cadence.
  startAudioPacer(s);

  // Resolve the workspace's active CALL FLOW (if the merchant built one).
  if (!s.assistantConfig.call_flow) {
    try {
      const wsId = s.workspaceId || s.assistantConfig?.workspace_id || 0;
      if (wsId) {
        const cfRes = await axios.post(`${s.appDomain}/api/call-flow/active`,
          { workspace_id: wsId },
          { timeout: 6000, headers: { 'X-Node-Token': process.env.NODE_WEBHOOK_TOKEN || '' } });
        if (cfRes.data?.flow) s.assistantConfig.call_flow = cfRes.data.flow;
      }
    } catch (e) { console.warn(`[WABA-BRIDGE] call-flow fetch failed: ${e?.message}`); }
  }

  // 6. Speak the greeting immediately so the caller doesn't sit in silence.
  //    If a CALL FLOW is bound to this number/assistant, the flow runs
  //    instead (it speaks its own Answer/Say nodes up to the first Listen).
  if (s.assistantConfig.call_flow && attachCallFlow(s, s.assistantConfig.call_flow)) {
    console.log(`[WABA-BRIDGE] call flow attached call=${s.metaCallId}`);
    await runFromStart(s, callFlowDeps(s));
  } else {
    const greetings = (s.assistantConfig.meta?.greeting_variations || [])
      .filter(g => (g || '').trim());
    const greet = greetings.length
      ? greetings[Math.floor(Math.random() * greetings.length)]
      : (s.assistantConfig.greeting_text || 'Hi, how can I help you today?');
    await speak(s, greet, 'agent');
  }

  // 7. Start STT streaming so subsequent caller speech is transcribed.
  openStt(s);
}

/**
 * Open the streaming STT connection. Uses Deepgram when a Deepgram key is
 * present (lowest latency), otherwise OpenAI's realtime transcription so a
 * workspace with only an OpenAI key can still run AI voice calls. Both feed
 * the same handleFinalTranscript() so the rest of the pipeline is identical.
 */
function openStt(s) {
  if (s.sttProvider === 'deepgram') return openSttDeepgram(s);
  if (s.sttProvider === 'openai')   return openSttOpenAI(s);
  console.warn(`[WABA-BRIDGE] no STT provider available — AI can't hear call=${s.metaCallId} (configure Deepgram OR OpenAI at /admin/api-keys).`);
}

/**
 * Deepgram realtime — Linear16 PCM @ 48 kHz mono (wrtc's native format, no
 * resample). Endpointing 600 ms = "if the caller pauses 600 ms, finalise."
 */
function openSttDeepgram(s) {
  const lang = (s.assistantConfig.meta?.languages || ['en'])[0] || 'en';
  const url = `wss://api.deepgram.com/v1/listen?encoding=linear16&sample_rate=48000&channels=1&interim_results=false&punctuate=true&endpointing=600&language=${encodeURIComponent(lang)}&model=nova-2`;
  const ws = new WebSocket(url, { headers: { Authorization: `Token ${s.deepgramKey}` } });
  s.sttSocket = ws;
  ws.on('open', () => {
    console.log(`[WABA-BRIDGE] Deepgram STT connected call=${s.metaCallId}`);
    s.sttSend = (buf48) => { if (ws.readyState === WebSocket.OPEN) ws.send(buf48); };
  });
  ws.on('message', async (data) => {
    try {
      const msg = JSON.parse(data.toString());
      const text = (msg.channel?.alternatives?.[0]?.transcript || '').trim();
      if (text && msg.is_final) await handleFinalTranscript(s, text);
    } catch (e) {
      console.warn(`[WABA-BRIDGE] Deepgram STT parse failed: ${e?.message}`);
    }
  });
  ws.on('error', e => console.warn(`[WABA-BRIDGE] Deepgram STT error: ${e?.message}`));
  ws.on('close', () => { s.sttSend = null; console.log(`[WABA-BRIDGE] Deepgram STT closed call=${s.metaCallId}`); });
}

/**
 * OpenAI realtime transcription — connect with intent=transcription, then
 * configure a session (pcm16 @ 24 kHz mono + server VAD) and stream base64
 * audio in. Completed-turn transcripts come back mirroring Deepgram's
 * is_final. wrtc gives 48 kHz so we downsample 2:1 to 24 kHz before sending.
 */
function openSttOpenAI(s) {
  // GA realtime API — the old Beta shape (OpenAI-Beta header +
  // transcription_session.update) is retired and errors with
  // "beta_api_shape_disabled". No OpenAI-Beta header; config via
  // session.update with a nested audio.input block.
  const url = 'wss://api.openai.com/v1/realtime?intent=transcription';
  const ws = new WebSocket(url, {
    headers: { Authorization: `Bearer ${s.openaiKey}` },
  });
  s.sttSocket = ws;
  ws.on('open', () => {
    console.log(`[WABA-BRIDGE] OpenAI STT connected call=${s.metaCallId}`);
    ws.send(JSON.stringify({
      type: 'session.update',
      session: {
        type: 'transcription',
        audio: {
          input: {
            format: { type: 'audio/pcm', rate: 24000 }, // 24 kHz mono LE PCM
            // PIN the language when the assistant declares one (same source the
            // Deepgram path uses). Pure auto-detect was the #1 cause of the
            // "starts / flips to a random language" complaint: gpt-4o-transcribe
            // mis-detects short/noisy turns, transcribes garbage in another
            // language, and the LLM then replies in it. Only when NO language is
            // configured do we let it auto-detect (multilingual assistants).
            transcription: {
              model: 'gpt-4o-transcribe',
              ...(((s.assistantConfig.meta?.languages || [])[0])
                ? { language: (s.assistantConfig.meta.languages[0] || 'en') }
                : {}),
            },
            // Higher threshold + longer end-silence = the VAD only fires on
            // clear, sustained speech. Stops brief noise / echo tails from
            // producing phantom transcripts (e.g. the classic "村です" silence
            // hallucination) that make the AI ask "please repeat" out of nowhere.
            turn_detection: { type: 'server_vad', threshold: 0.72, prefix_padding_ms: 300, silence_duration_ms: 900 },
          },
        },
      },
    }));
    s.sttSend = (buf48) => {
      if (ws.readyState !== WebSocket.OPEN) return;
      const pcm24 = downsample48to24(buf48);
      ws.send(JSON.stringify({ type: 'input_audio_buffer.append', audio: pcm24.toString('base64') }));
    };
  });
  ws.on('message', async (data) => {
    try {
      const msg = JSON.parse(data.toString());
      const type = msg.type || '';
      if (type === 'error') {
        console.warn(`[WABA-BRIDGE] OpenAI STT error: ${JSON.stringify(msg.error || msg)}`);
        return;
      }
      // Final transcript for a completed caller turn. Tolerant of minor
      // event-name changes across API versions.
      if (type.includes('input_audio_transcription') && type.endsWith('.completed')) {
        const text = (msg.transcript || '').trim();
        if (text) await handleFinalTranscript(s, text);
      }
    } catch (e) {
      console.warn(`[WABA-BRIDGE] OpenAI STT parse failed: ${e?.message}`);
    }
  });
  ws.on('error', e => console.warn(`[WABA-BRIDGE] OpenAI STT socket error: ${e?.message}`));
  ws.on('close', () => { s.sttSend = null; console.log(`[WABA-BRIDGE] OpenAI STT closed call=${s.metaCallId}`); });
}

/**
 * Shared final-transcript handler for both STT providers: record the turn,
 * let a call flow drive it, honour exit keywords, else ask the LLM + speak.
 */
async function handleFinalTranscript(s, text) {
  if (!text) return;
  // Half-duplex guard: drop anything transcribed WHILE the AI is speaking.
  // The audio feed is already muted during speech, but a transcript can be
  // in-flight — ignoring it here stops the AI answering its own echoed voice
  // and looping in random languages ("Hola" / "嘿喲" / hallucinated lines).
  if (s.speaking) {
    console.log(`[WABA-BRIDGE] ignored (AI speaking): "${text}"`);
    return;
  }
  console.log(`[WABA-BRIDGE] user said: "${text}"`);
  recordTurn(s, 'user', text);
  s.speaking = true; // claim the floor through the LLM + TTS

  try {
    // Call flow drives the turn when one is active (handles its own goodbye).
    if (hasCallFlow(s)) {
      await handleTurn(s, text, callFlowDeps(s));
      return;
    }

    // Exit keywords — say goodbye + hang up.
    const exit = (s.assistantConfig.exit_keywords || []).map(k => k.toLowerCase());
    if (exit.some(k => text.toLowerCase().includes(k))) {
      await speak(s, s.assistantConfig.last_greeting || 'Thank you for calling. Goodbye!', 'agent');
      setTimeout(() => closeSession(null, s.metaCallId), 1500);
      return;
    }

    // Generate AI reply (Laravel handles provider + admin keys + tools).
    // One retry on an empty/failed result (transient network blip), then a
    // spoken reprompt so the caller NEVER hears dead air.
    let reply = await generateReply(s, text);
    if (!reply) {
      await new Promise(r => setTimeout(r, 250));
      reply = await generateReply(s, text);
    }
    if (reply) {
      await speak(s, reply, 'agent');
    } else {
      console.warn(`[WABA-BRIDGE] empty AI reply after retry — speaking reprompt call=${s.metaCallId}`);
      await speak(s, s.assistantConfig.reprompt_text || 'Sorry, I missed that. Could you please say it again?', 'agent');
    }
  } finally {
    // speak() already releases the floor after its play-out; this also
    // releases it when we produced no audio (empty reply / call-flow path)
    // so the AI doesn't stay deaf for the rest of the call.
    s.speaking = false;
  }
}

/**
 * Downsample 48 kHz Int16 mono PCM → 24 kHz by averaging adjacent sample
 * pairs (cheap anti-alias). OpenAI realtime input wants pcm16 @ 24 kHz.
 */
function downsample48to24(buf48) {
  const inS = new Int16Array(buf48.buffer, buf48.byteOffset, Math.floor(buf48.byteLength / 2));
  const out = new Int16Array(Math.floor(inS.length / 2));
  for (let i = 0, j = 0; j < out.length; i += 2, j++) {
    out[j] = (inS[i] + inS[i + 1]) >> 1;
  }
  return Buffer.from(out.buffer, out.byteOffset, out.byteLength);
}

/**
 * Primitives the call-flow walker (callFlowRuntime.js) uses to drive the
 * call. All voice goes through the existing speak()/TTS path; AI + search
 * go through Laravel so keys stay server-side.
 */
function callFlowDeps(s) {
  const token = process.env.NODE_WEBHOOK_TOKEN || '';
  const wsId  = s.workspaceId || s.assistantConfig?.workspace_id || 0;
  return {
    speak: (text) => speak(s, text, 'agent'),
    aiReply: async (node) => {
      try {
        const history = (s.transcript || []).slice(-12)
          .map(t => (t.role === 'user' ? 'Customer' : 'Agent') + ': ' + t.text).join('\n');
        const userPrompt = (history ? history + '\n' : '')
          + 'Customer: ' + (s.callFlow?.vars?.__lastTurn || '') + '\nAgent:';
        const res = await axios.post(`${s.appDomain}/api/flow-node/ai-call`, {
          workspace_id: wsId,
          model: node.model || 'gpt-4o-mini',
          system_prompt: node.prompt || 'You are a helpful phone assistant. Keep replies short and natural.',
          user_prompt: userPrompt,
          ...(node.assistantId ? { assistant_id: node.assistantId } : {}),
          // Call Flow "AI Assistant" node → reply with the chosen voice agent's persona.
          ...(node.callAssistantId ? { call_assistant_id: node.callAssistantId } : {}),
          max_tokens: 200, temperature: 0.6,
        }, { timeout: 12000, headers: { 'X-Node-Token': token } });
        return String(res.data?.reply || '');
      } catch (e) { console.warn(`[CALLFLOW] ai failed: ${e?.message}`); return ''; }
    },
    webSearch: async (query) => {
      try {
        const res = await axios.post(`${s.appDomain}/api/flow-node/web-search`,
          { query, max_results: 5 },
          { timeout: 10000, headers: { 'X-Node-Token': token } });
        return String(res.data?.text || '');
      } catch (e) { console.warn(`[CALLFLOW] search failed: ${e?.message}`); return ''; }
    },
    endCall: () => { setTimeout(() => closeSession(null, s.metaCallId), 1200); },
    // Meta call transfer isn't wired yet → return false so the flow takes
    // the "if no answer" path instead of dropping the caller.
    transfer: async () => false,
    callerSaidGoodbye: () => {
      const exit = (s.assistantConfig.exit_keywords || []).map(k => k.toLowerCase());
      const said = String(s.callFlow?.vars?.__lastTurn || '').toLowerCase();
      return exit.some(k => said.includes(k));
    },
  };
}

/**
 * The assistant's own system prompt PLUS hard phone-call rules that stop the
 * three complaints: language flipping, rambling/irrelevant answers, and not
 * understanding. The caller's own prompt stays first (their persona wins);
 * these rules are appended as non-negotiable guardrails.
 */
/** The assistant's selected "Spoken languages" as readable names (empty = any). */
function selectedLangNames(s) {
  const raw = s.assistantConfig?.meta?.languages;
  if (!Array.isArray(raw) || raw.length === 0) return [];
  const MAP = { en: 'English', hi: 'Hindi', es: 'Spanish', fr: 'French', de: 'German',
    ar: 'Arabic', pt: 'Portuguese', id: 'Indonesian', ur: 'Urdu', bn: 'Bengali',
    ta: 'Tamil', te: 'Telugu', mr: 'Marathi', gu: 'Gujarati', ru: 'Russian',
    tr: 'Turkish', it: 'Italian', ja: 'Japanese', zh: 'Chinese' };
  return raw.map(x => MAP[String(x || '').toLowerCase()] || String(x)).filter(Boolean);
}

function buildCallSystemPrompt(s) {
  const base = (s.assistantConfig?.ai_system_prompt || '').trim()
    || 'You are a helpful voice assistant answering a phone call for this business.';
  // Use the assistant's selected languages to steer the reply language. When set
  // (e.g. English + Hindi), the AI keys off the caller's latest message but stays
  // within that set — which also stops the Hindi↔Urdu drift because Urdu isn't
  // selected. When none are selected, fall back to pure auto-detect (any language).
  const langs = selectedLangNames(s);
  const langLine = langs.length
    ? `- This assistant is set up for these languages: ${langs.join(', ')}. The caller will speak one of them — detect which from what they JUST said and reply in that same language, matching their latest message (they may switch between the set mid-call, so follow them each time). Only if the caller clearly and repeatedly speaks a language that is NOT in that list should you switch to it.`
    : `- Always reply in the language of the caller's LATEST message — detect what they just said and answer in THAT language. This applies in BOTH directions, including switching BACK to a language they used earlier. If they speak English now, reply in English; if they then speak Hindi, reply in Hindi; if they go back to English, reply in English again. Ignore what language the earlier part of the call was in — match the newest message.`;
  return base + `

You are on a LIVE PHONE CALL. Follow these rules strictly:
${langLine}
- The ONLY exception: Hindi and Urdu (and other closely related spoken languages) sound identical on a phone line, so the transcription may render the SAME speech in either script. A flip between those two scripts is NOT a real language change — keep the script you were already using.
- If a message looks like an unexpected language but is short, out of place, or nonsensical, treat it as a mis-transcription: keep your current language or briefly ask them to repeat.
- Keep every reply to ONE short, natural sentence (two at most). The caller is listening, not reading — no lists, no long explanations.
- Answer only what was asked and stay on topic. Do not volunteer unrelated information or repeat yourself.`;
}

/**
 * Build the LLM prompt from the assistant config + transcript history
 * and call Laravel's `/api/flow-node/ai-call` (already wired with admin
 * keys via AiKeyResolver). Returns the reply text.
 */
async function generateReply(s, userText) {
  try {
    const history = s.transcript
      .slice(-12)
      .map(t => (t.role === 'user' ? 'Customer' : 'Agent') + ': ' + t.text)
      .join('\n');
    const userPrompt = (history ? history + '\n' : '') + 'Customer: ' + userText + '\nAgent:';

    const r = await axios.post(
      `${s.appDomain}/api/flow-node/ai-call`,
      {
        workspace_id:  s.workspaceId,
        model:         s.assistantConfig.ai_model,
        system_prompt: buildCallSystemPrompt(s),
        user_prompt:   userPrompt,
        max_tokens:    150,   // short spoken replies — lower latency + less rambling
        temperature:   0.4,   // more focused / on-topic
      },
      { timeout: 12000, headers: { 'X-Node-Token': s.nodeToken } },
    );
    return String(r.data?.reply || '').trim();
  } catch (e) {
    console.warn(`[WABA-BRIDGE] LLM call failed: ${e?.response?.data?.message || e?.message}`);
    return '';
  }
}

/**
 * Convert text → audio frames → push into the outbound MediaStreamTrack.
 *
 * Uses ElevenLabs TTS streaming WebSocket so the first audio chunk
 * comes back in ~200 ms. Each chunk is base64 PCM @ 16 kHz mono; we
 * upsample 3x to 48 kHz so wrtc accepts it (mono Opus @ 48 kHz is the
 * implicit codec after WebRTC's audio path).
 */
async function speak(s, text, role) {
  if (!text || !s.audioSource) return;
  // Claim the floor: while true, the STT feed is muted and any transcript is
  // ignored (see the audioSink handler + handleFinalTranscript). Without this
  // the caller's phone echoes the AI's voice into their mic, the STT
  // transcribes the AI's OWN words (as random-language hallucinations), and
  // the AI answers itself in a loop.
  s.speaking = true;
  s.interrupted = false;        // fresh floor — clear any previous barge-in
  s._speakStartAt = Date.now(); // barge-in ignores the first BARGE_MIN_SPEAK_MS
  s._loudMs = 0;
  recordTurn(s, role, text);
  console.log(`[WABA-BRIDGE] agent says: "${text}"`);

  // Agent-side recording opens on first speech, but ONLY once the operator
  // has armed recording from the call popup. See startRecording().
  if (s.assistantConfig.record_agent && s.recordingArmed && !s.recAgent) {
    s.recAgent = fs.createWriteStream(path.join(RECORDING_DIR, `${s.metaCallId}_agent.pcm`));
  }

  // Prefer ElevenLabs (streaming, low latency). If it yields no audio —
  // usually because PCM output needs a PAID ElevenLabs tier — fall back to
  // OpenAI TTS (same key as STT + the LLM). Once ElevenLabs proves unusable
  // on this call we skip it to avoid the per-reply delay.
  let produced = 0;
  if (!s.ttsFallbackOpenAI && s.elevenlabsKey) {
    produced = await speakElevenLabs(s, text);
    if (produced === 0) {
      // Log the REAL reason ElevenLabs returned nothing so the operator can
      // fix it (wrong/free API key, or a library voice not on the account),
      // instead of a generic guess. Falls back to OpenAI so the call still works.
      const why = s._elevenLabsError
        ? `ElevenLabs rejected the request: ${s._elevenLabsError}`
        : 'ElevenLabs produced no audio (check the API key is your PAID account + the voice is a premade/added voice)';
      console.warn(`[WABA-BRIDGE] ${why} — falling back to OpenAI TTS. ElevenLabs credits will NOT be used until this is fixed. call=${s.metaCallId}`);
      s.ttsFallbackOpenAI = true;
    }
  }
  if (produced === 0 && s.openaiKey) {
    produced = await speakOpenAiTts(s, text);
    console.log(`[WABA-BRIDGE] OpenAI TTS produced ${produced} bytes for "${text.slice(0, 40)}" call=${s.metaCallId}`);
  }
  if (produced === 0) {
    console.warn(`[WABA-BRIDGE] no working TTS (ElevenLabs empty + no OpenAI key) — silent reply. Configure a key at /admin/api-keys.`);
  }

  // Caller barged in mid-reply — the floor was already released and the queue
  // dropped, so don't wait or re-mute; the caller's turn is already being heard.
  if (s.interrupted) return;

  // Wait out the audio still queued to play (48 kHz mono 16-bit = 96 bytes/ms)
  // + a short grace, so we don't start listening to our own tail echo, then
  // release the floor for the caller. Poll so a barge-in during the tail
  // breaks out immediately instead of holding the caller off.
  const queued = (s.audioChunks || []).reduce((n, c) => n + c.length, 0) - (s.audioChunkOff || 0);
  const waitMs = Math.max(0, Math.round(queued / 96)) + 400;
  const until = Date.now() + waitMs;
  while (Date.now() < until) {
    if (s.interrupted || s.closed) return;
    await new Promise((r) => setTimeout(r, 20));
  }
  if (!s.interrupted) s.speaking = false;
}

/**
 * ElevenLabs streaming TTS → 48 kHz PCM enqueued for the pacer. Returns the
 * count of 16 kHz PCM bytes received (0 = produced nothing → caller falls back).
 */
async function speakElevenLabs(s, text) {
  s._elevenLabsError = null;   // capture the reason if it rejects (surfaced in speak())
  const apiKey = s.elevenlabsKey;
  const voiceId = s.assistantConfig.voice_id || '21m00Tcm4TlvDq8ikWAM'; // "Rachel"
  const wsUrl = `wss://api.elevenlabs.io/v1/text-to-speech/${voiceId}/stream-input?model_id=eleven_flash_v2_5&output_format=pcm_16000`;
  const ws = new WebSocket(wsUrl, { headers: { 'xi-api-key': apiKey } });

  try {
    await new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error('open timeout')), 8000);
      ws.once('open', () => { clearTimeout(timer); resolve(); });
      ws.once('error', e => { clearTimeout(timer); reject(e); });
    });
  } catch (e) {
    console.warn(`[WABA-BRIDGE] ElevenLabs open failed: ${e?.message}`);
    return 0;
  }

  ws.send(JSON.stringify({ text: ' ', voice_settings: { stability: 0.5, similarity_boost: 0.8 } }));
  ws.send(JSON.stringify({ text }));
  ws.send(JSON.stringify({ text: '', flush: true }));

  let ttsBytes = 0;
  // Let a barge-in close the socket mid-stream (stops audio at once).
  s._ttsAbort = () => { try { ws.close(); } catch {} };
  await new Promise((resolve) => {
    ws.on('message', (data) => {
      try {
        if (s.interrupted) { try { ws.close(); } catch {}; resolve(); return; }
        const msg = JSON.parse(data.toString());
        if (msg.audio) {
          const pcm16k = Buffer.from(msg.audio, 'base64');
          ttsBytes += pcm16k.length;
          const pcm48k = upsample3x(pcm16k);
          s.recAgent?.write(pcm48k);
          enqueueAudio(s, pcm48k);
        } else if (msg.error || msg.message) {
          s._elevenLabsError = String(msg.message || msg.error || '').slice(0, 180);
          // Include the masked key + voice id so it's obvious WHICH credential
          // ElevenLabs rejected (match it to the [WABA-BRIDGE] keys line above).
          const _m = (k) => (k && k.length) ? `${k.slice(0, 3)}…${k.slice(-3)} len${k.length}` : 'ABSENT';
          console.warn(`[WABA-BRIDGE] ElevenLabs REJECTED key=${_m(s.elevenlabsKey)} voice=${s.assistantConfig?.voiceId || s.assistantConfig?.meta?.voice_id || '?'} → ${JSON.stringify(msg).slice(0, 200)}`);
        }
        if (msg.isFinal) { ws.close(); resolve(); }
      } catch (e) { /* ignore parse errors */ }
    });
    ws.on('close', resolve);
    ws.on('error', e => { console.warn(`[WABA-BRIDGE] ElevenLabs ws error: ${e?.message}`); resolve(); });
  });
  s._ttsAbort = null;
  return ttsBytes;
}

/**
 * OpenAI TTS (/v1/audio/speech) → 24 kHz PCM → upsample to 48 kHz → enqueue.
 * Non-streaming (one HTTP call) but works on any OpenAI key. Returns bytes.
 */
async function speakOpenAiTts(s, text) {
  try {
    const r = await axios.post(
      'https://api.openai.com/v1/audio/speech',
      { model: 'gpt-4o-mini-tts', voice: 'alloy', input: text, response_format: 'pcm' },
      {
        headers: { Authorization: `Bearer ${s.openaiKey}`, 'Content-Type': 'application/json' },
        // STREAM the audio: enqueue 24 kHz PCM chunks as they arrive so the
        // caller hears the AI in ~300 ms instead of waiting for the whole
        // reply to render (~1-2 s) — the main "slow/laggy" complaint.
        responseType: 'stream',
        timeout: 30000,
      },
    );
    let total = 0;
    let rem = Buffer.alloc(0); // carry an odd trailing byte into the next chunk
    // Let a barge-in kill the stream mid-flight (stops audio + saves tokens).
    s._ttsAbort = () => { try { r.data.destroy(); } catch {} };
    await new Promise((resolve, reject) => {
      r.data.on('data', (chunk) => {
        if (s.interrupted) { try { r.data.destroy(); } catch {} return; }
        const buf = rem.length ? Buffer.concat([rem, chunk]) : chunk;
        const usable = buf.length - (buf.length % 2);
        rem = usable < buf.length ? Buffer.from(buf.subarray(usable)) : Buffer.alloc(0);
        if (usable <= 0) return;
        const pcm24 = buf.subarray(0, usable);
        total += usable;
        const pcm48 = upsample2x(pcm24);
        s.recAgent?.write(pcm48);
        enqueueAudio(s, pcm48);
      });
      r.data.on('end', resolve);
      r.data.on('close', resolve); // barge-in destroy() emits 'close' — don't hang
      r.data.on('error', resolve); // aborted/destroyed stream resolves, not throws
    });
    s._ttsAbort = null;
    return total;
  } catch (e) {
    console.warn(`[WABA-BRIDGE] OpenAI TTS failed (${e?.response?.status || '?'}): ${e?.message || 'error'}`);
    return 0;
  }
}

/**
 * Return an Int16Array view of a Buffer, guaranteeing 2-byte alignment.
 * Node Buffers from the pool (and streamed HTTP chunks) can start at an ODD
 * byteOffset, which makes a direct `new Int16Array(buf.buffer, byteOffset)`
 * throw / read garbage → distorted audio. Copy into a fresh aligned buffer
 * only when needed (cheap; the common case is already aligned).
 */
function toInt16(buf) {
  const n = Math.floor(buf.byteLength / 2);
  if ((buf.byteOffset % 2) === 0) {
    return new Int16Array(buf.buffer, buf.byteOffset, n);
  }
  const ab = new ArrayBuffer(n * 2);
  new Uint8Array(ab).set(new Uint8Array(buf.buffer, buf.byteOffset, n * 2));
  return new Int16Array(ab);
}

/** Downmix interleaved N-channel Int16 PCM → mono (average the channels). */
function downmixToMono(buf, ch) {
  const inS = toInt16(buf);
  const outLen = Math.floor(inS.length / ch);
  const out = new Int16Array(outLen);
  for (let i = 0; i < outLen; i++) {
    let sum = 0;
    for (let c = 0; c < ch; c++) sum += inS[i * ch + c] || 0;
    out[i] = (sum / ch) | 0;
  }
  return Buffer.from(out.buffer, out.byteOffset, out.byteLength);
}

/** Resample Int16 mono PCM between arbitrary rates (linear interpolation). */
function resampleLinear(buf, fromRate, toRate) {
  if (!fromRate || fromRate === toRate) return buf;
  const inS = toInt16(buf);
  const ratio = toRate / fromRate;
  const outLen = Math.max(1, Math.floor(inS.length * ratio));
  const out = new Int16Array(outLen);
  for (let i = 0; i < outLen; i++) {
    const src = i / ratio;
    const idx = Math.floor(src);
    const frac = src - idx;
    const a = inS[idx] || 0;
    const b = (idx + 1 < inS.length) ? inS[idx + 1] : a;
    out[i] = (a + (b - a) * frac) | 0;
  }
  return Buffer.from(out.buffer, out.byteOffset, out.byteLength);
}

/** Upsample 24 kHz Int16 mono PCM → 48 kHz (2x, linear interpolation). */
function upsample2x(buf24k) {
  const inS = toInt16(buf24k);
  const out = new Int16Array(inS.length * 2);
  for (let i = 0; i < inS.length - 1; i++) {
    out[i * 2]     = inS[i];
    out[i * 2 + 1] = (inS[i] + inS[i + 1]) >> 1;
  }
  const last = inS.length ? inS[inS.length - 1] : 0;
  out[out.length - 2] = last;
  out[out.length - 1] = last;
  return Buffer.from(out.buffer, out.byteOffset, out.byteLength);
}

// --- Barge-in tuning (caller interrupts the AI mid-sentence) ---------------
// Deliberately conservative so residual phone echo never triggers a false
// interrupt: the caller's direct voice is far louder than post-AEC echo, and
// echo doesn't sustain. Bump BARGE_RMS_MIN up if soft echo ever cuts the AI
// off; drop it if a real interruption is ignored.
const BARGE_RMS_MIN     = 1800;  // Int16 RMS; normal phone speech ~2000-6000
const BARGE_SUSTAIN_MS  = 300;   // must stay loud this long = real speech
const BARGE_MIN_SPEAK_MS = 700;  // ignore the AI's own first 0.7 s (no self-cut)

/** RMS loudness of a mono Int16 PCM buffer (0 = silence). */
function rmsInt16(buf) {
  const s16 = toInt16(buf);
  if (!s16.length) return 0;
  let sum = 0;
  for (let i = 0; i < s16.length; i++) sum += s16[i] * s16[i];
  return Math.sqrt(sum / s16.length);
}

/**
 * Caller talked over the AI — stop speaking immediately: drop the queued
 * audio, abort the in-flight TTS (so it stops streaming + stops burning
 * tokens), and release the floor so the caller's words feed the transcriber.
 */
function triggerBargeIn(s) {
  if (!s.speaking || s.interrupted) return;
  console.log(`[WABA-BRIDGE] barge-in: caller interrupted — stopping AI call=${s.metaCallId}`);
  s.interrupted = true;
  try { s._ttsAbort?.(); } catch {}
  s.audioChunks = [];
  s.audioChunkOff = 0;
  s._loudMs = 0;
  s.speaking = false;
}

/**
 * Enqueue 48 kHz mono Int16 PCM as a CHUNK (no concat). The old queue did
 * Buffer.concat on every streamed TTS chunk (O(n²)) — CPU spikes made the
 * 10 ms clock jitter, so LIVE playback was laggy/distorted even though the
 * recording (a plain sequential write) sounded clean. A chunk list + a
 * drift-corrected clock makes the live audio match the recording.
 */
function enqueueAudio(s, pcm48k) {
  if (!s.audioSource || s.closed || s.interrupted) return;
  if (!s.audioChunks) { s.audioChunks = []; s.audioChunkOff = 0; }
  s.audioChunks.push(Buffer.from(pcm48k)); // copy — source buffer may be reused
  startAudioPacer(s);
}

/** Pull exactly `need` bytes from the chunk queue (amortised O(1), no concat). */
function pullAudioFrame(s, need) {
  const chunks = s.audioChunks;
  if (!chunks || !chunks.length) return null;
  let off = s.audioChunkOff || 0;
  if (chunks[0].length - off >= need) {            // fast path: head has enough
    const frame = chunks[0].subarray(off, off + need);
    off += need;
    if (off >= chunks[0].length) { chunks.shift(); off = 0; }
    s.audioChunkOff = off;
    return frame;
  }
  const out = Buffer.allocUnsafe(need);            // slow path: span chunks
  let filled = 0;
  while (filled < need && chunks.length) {
    const c = chunks[0];
    const take = Math.min(need - filled, c.length - off);
    c.copy(out, filled, off, off + take);
    filled += take; off += take;
    if (off >= c.length) { chunks.shift(); off = 0; }
  }
  s.audioChunkOff = off;
  if (filled < need) out.fill(0, filled);          // underrun → pad silence
  return out;
}

/**
 * Drift-corrected 10 ms audio clock. Delivers frames based on REAL elapsed
 * wall-clock time — a naive setInterval(10) fires late under load and slowly
 * de-syncs, so long messages fell behind real time (the accumulating "lag"
 * + distortion). Here we compute how many 10 ms frames are DUE by now and
 * ship exactly that many, so live audio stays sample-accurate like the
 * recording. Silence fills gaps to keep the RTP stream alive.
 */
function startAudioPacer(s) {
  if (s.audioPacer) return;
  const FRAME_SAMPLES = 480;              // 10 ms @ 48 kHz
  const FRAME_BYTES   = FRAME_SAMPLES * 2;
  const FRAME_MS      = 10;
  const silence = Buffer.alloc(FRAME_BYTES);
  s._pacerStart = Date.now();
  s._framesSent = 0;

  s.audioPacer = setInterval(() => {
    if (s.closed || !s.audioSource) return;
    const due = Math.floor((Date.now() - s._pacerStart) / FRAME_MS);
    let toSend = due - s._framesSent;
    if (toSend <= 0) return;
    if (toSend > 60) toSend = 60;          // cap a catch-up burst at 0.6 s

    for (let i = 0; i < toSend; i++) {
      const frame = pullAudioFrame(s, FRAME_BYTES) || silence;
      // Copy into a fresh, 2-byte-aligned Int16Array (Buffer pool byteOffsets
      // can be odd, which would make an Int16Array view throw).
      const samples = new Int16Array(FRAME_SAMPLES);
      new Uint8Array(samples.buffer).set(new Uint8Array(frame.buffer, frame.byteOffset, FRAME_BYTES));
      try {
        s.audioSource.onData({
          samples,
          sampleRate: 48000,
          channelCount: 1,
          bitsPerSample: 16,
          numberOfFrames: FRAME_SAMPLES,
        });
      } catch (e) {
        // Source detached mid-tick — closeSession clears this interval.
      }
      s._framesSent++;
    }
  }, 5);
}

/**
 * Crude 3x linear interpolation upsample from 16 kHz to 48 kHz.
 * Good enough for telephony quality; no aliasing artefacts that matter
 * once Opus re-encodes for the WebRTC peer.
 */
function upsample3x(buf16k) {
  const inSamples = toInt16(buf16k);
  const out = new Int16Array(inSamples.length * 3);
  for (let i = 0; i < inSamples.length - 1; i++) {
    const a = inSamples[i];
    const b = inSamples[i + 1];
    out[i * 3]     = a;
    out[i * 3 + 1] = a + Math.round((b - a) / 3);
    out[i * 3 + 2] = a + Math.round(2 * (b - a) / 3);
  }
  out[out.length - 3] = out[out.length - 4];
  out[out.length - 2] = out[out.length - 4];
  out[out.length - 1] = out[out.length - 4];
  return Buffer.from(out.buffer);
}

/**
 * Record a transcript turn locally + send to Laravel so /call-logs
 * shows the conversation as it unfolds.
 */
function recordTurn(s, role, text) {
  if (!text) return;
  const tMs = Date.now() - s.startedAt;
  s.transcript.push({ role, text, t: tMs });
  axios.post(
    `${s.appDomain}/api/waba-call/transcript-turn`,
    { wa_call_id: s.waCallId, role, text, t_ms: tMs },
    { timeout: 5000, headers: { 'X-Node-Token': s.nodeToken } },
  ).catch(e => console.warn(`[WABA-BRIDGE] turn record failed: ${e?.message}`));
}

/**
 * Call Meta's Graph API to pre_accept / accept / reject / terminate.
 */
async function metaAction(s, action, sdp = null) {
  if (!s.metaToken || !s.phoneNumberId) {
    console.warn(`[WABA-BRIDGE] no Meta token / phone_number_id for call=${s.metaCallId} — cannot ${action}. Workspace's WaProviderConfig is missing credentials.`);
    return false;
  }
  const url = `https://graph.facebook.com/${s.graphVersion}/${s.phoneNumberId}/calls`;
  const body = {
    messaging_product: 'whatsapp',
    call_id: s.metaCallId,
    action,
  };
  if (sdp) body.session = { sdp_type: 'answer', sdp };
  s._lastMetaError = null;
  try {
    const r = await axios.post(url, body, {
      timeout: 8000,
      headers: { Authorization: `Bearer ${s.metaToken}`, 'Content-Type': 'application/json' },
    });
    if (r.data?.success === true) {
      console.log(`[WABA-BRIDGE] meta ${action} ok call=${s.metaCallId}`);
      return true;
    }
    s._lastMetaError = r.data?.error || null;
    console.warn(`[WABA-BRIDGE] meta ${action} response not success`, r.data);
    return false;
  } catch (e) {
    // Stash Meta's error so the caller can distinguish an already-in-progress
    // 131055 (a redundant action after pre_accept — safe to ignore) from a real
    // failure that must abort the call.
    s._lastMetaError = e?.response?.data?.error || null;
    console.error(`[WABA-BRIDGE] meta ${action} failed: ${e?.response?.data ? JSON.stringify(e.response.data) : e?.message}`);
    return false;
  }
}

/**
 * Close session — flush sockets, close recordings, post terminate to
 * Meta. Idempotent so concurrent close paths (caller hangup vs our
 * exit-keyword detection) don't double-fire.
 */
export function closeSession(app, metaCallId) {
  const s = sessions.get(metaCallId);
  if (!s || s.closed) return;
  s.closed = true;

  try { clearInterval(s.audioPacer); } catch {}
  try { s.sttSocket?.close?.(); } catch {}
  try { s.ttsSocket?.close?.(); } catch {}
  try { s.audioSink?.stop?.(); } catch {}
  try { s.pc?.close?.(); } catch {}
  // Log recorded sizes so a bad-length recording is diagnosable at a glance:
  // both streams are 48 kHz mono 16-bit → 96000 bytes/sec. If ~seconds here
  // is far off the real call length, the caller-track capture is the culprit.
  try {
    const uB = s.recUser?.bytesWritten || 0;
    const aB = s.recAgent?.bytesWritten || 0;
    console.log(`[WABA-BRIDGE] recording bytes user=${uB} (~${Math.round(uB / 96000)}s) agent=${aB} (~${Math.round(aB / 96000)}s) callerRate=${s._effRate || '?'} call=${metaCallId}`);
  } catch {}
  try { s.recUser?.end?.(); } catch {}
  try { s.recAgent?.end?.(); } catch {}

  // Best-effort terminate so Meta knows we hung up. Skip when WS-driven
  // close (Meta sent us terminate first).
  metaAction(s, 'terminate').catch(() => {});

  console.log(`[WABA-BRIDGE] closed call=${metaCallId} duration=${Math.floor((Date.now() - s.startedAt) / 1000)}s turns=${s.transcript.length}`);
  sessions.delete(metaCallId);
}

export function activeSessions() {
  return Array.from(sessions.values());
}

/**
 * Wait until ICE gathering finishes (state === 'complete') OR until the
 * timeout fires. Meta's calling API doesn't support trickle ICE, so the
 * answer SDP must carry all candidates before we POST it.
 */
function waitForIceGatheringComplete(pc, timeoutMs) {
  return new Promise((resolve) => {
    if (!pc) return resolve();
    if (pc.iceGatheringState === 'complete') return resolve();
    let done = false;
    const finish = () => { if (done) return; done = true; pc.removeEventListener?.('icegatheringstatechange', onChange); resolve(); };
    const onChange = () => { if (pc.iceGatheringState === 'complete') finish(); };
    pc.addEventListener?.('icegatheringstatechange', onChange);
    setTimeout(finish, Math.max(500, timeoutMs));
  });
}

/**
 * Wait until the RTCPeerConnection reaches `connected` (or `completed`).
 * Returns even on failure so the caller can still try to push audio —
 * occasionally Meta accepts the call before our local state flips.
 */
function waitForPeerConnected(pc, timeoutMs) {
  return new Promise((resolve) => {
    if (!pc) return resolve();
    const isUp = () => ['connected', 'completed'].includes(pc.connectionState)
      || ['connected', 'completed'].includes(pc.iceConnectionState);
    if (isUp()) return resolve();
    let done = false;
    const finish = () => { if (done) return; done = true; pc.removeEventListener?.('connectionstatechange', onChange); pc.removeEventListener?.('iceconnectionstatechange', onChange); resolve(); };
    const onChange = () => { if (isUp()) finish(); };
    pc.addEventListener?.('connectionstatechange', onChange);
    pc.addEventListener?.('iceconnectionstatechange', onChange);
    setTimeout(finish, Math.max(1000, timeoutMs));
  });
}

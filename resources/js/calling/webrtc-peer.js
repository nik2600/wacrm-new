// WaDesk WABA WebRTC peer
//
// One JS class wraps everything the operator's browser needs to do
// for a WhatsApp call:
//
//   - Generate / accept SDP via RTCPeerConnection
//   - Capture mic via getUserMedia
//   - Play the remote audio track via a hidden <audio> element
//   - Talk to our action endpoints (/wa-calling/calls/{id}/...)
//
// The signalling channel is plain HTTPS — every SDP/ICE swap rides
// over the same JSON endpoints that the rest of the team-inbox uses.
// No socket, no Reverb, no queue. ICE candidates are gathered
// non-trickle (one shot) so we don't need a bi-directional stream.
//
// Two entry points:
//
//   peer.dial({ configId, to, contactId?, conversationId? })
//   peer.answer({ callId, sdpOffer })
//
// Both return a Promise that resolves when audio is bridged. After
// that, peer.hangup() terminates and tears down.
//
// Usage:
//   import { WaCallPeer } from './calling/webrtc-peer.js';
//   const peer = new WaCallPeer({ onState: s => updateUi(s) });
//   await peer.answer({ callId: 42, sdpOffer: '...' });

// ICE servers decide how the two ends find a media path to each other.
// Multiple public STUN servers give redundancy so a single one being
// slow/blocked doesn't stall candidate gathering (which shows up as
// "voice cuts in and out" or one-way audio). For operators behind a
// strict / symmetric NAT, audio needs a TURN *relay* — the admin can
// inject TURN credentials at runtime via window.WA_CALL_ICE_SERVERS
// (emitted from a blade / system setting) WITHOUT rebuilding this file.
const DEFAULT_ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun2.l.google.com:19302' },
    { urls: 'stun:global.stun.twilio.com:3478' },
];

function iceServers() {
    try {
        const injected = window.WA_CALL_ICE_SERVERS;
        if (Array.isArray(injected) && injected.length) return injected;
    } catch (_) { /* no override present — use defaults */ }
    return DEFAULT_ICE_SERVERS;
}

function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

/**
 * Normalise an SDP blob so Chrome's RTCPeerConnection parser accepts it.
 *
 * Chrome's SDP parser is strict: every line MUST end with CRLF (\r\n) and
 * the blob must end with a line terminator. Meta's WhatsApp Calling API
 * returns its SDP answer/offer with bare LF (\n) line endings, which after
 * JSON transit reaches the browser as-is and makes setRemoteDescription
 * throw "Failed to parse SessionDescription ... Invalid SDP line". We fold
 * every line ending to CRLF and guarantee a trailing CRLF.
 */
function normalizeSdp(sdp, { coerceActiveSetup = false } = {}) {
    if (!sdp) return sdp;
    let s = String(sdp).replace(/\r\n/g, '\n').replace(/\r/g, '\n'); // → all LF

    if (coerceActiveSetup) {
        // Meta's WhatsApp Calling *answer* often carries `a=setup:actpass`,
        // which is illegal in an SDP answer (an answer MUST commit to a
        // concrete DTLS role — active or passive). Chrome may accept the
        // line but then the DTLS handshake never completes, so the call
        // shows "connected" with ZERO audio either way. Pin it to the
        // client role (browser becomes passive/server) so DTLS actually
        // runs and media flows. Only rewrites the ambiguous form — a
        // concrete active/passive from Meta is left untouched.
        s = s.replace(/^a=setup:actpass[ \t]*$/gim, 'a=setup:active');
    }

    s = s.replace(/\n/g, '\r\n');                                    // → all CRLF
    if (!s.endsWith('\r\n')) s += '\r\n';
    return s;
}

async function api(path, opts = {}) {
    const res = await fetch(path, {
        ...opts,
        credentials: 'same-origin',
        headers: {
            ...(opts.headers || {}),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data?.error || data?.message || `HTTP ${res.status}`);
    return data;
}

export class WaCallPeer {
    constructor({ onState = null, onError = null } = {}) {
        this.onState = onState || (() => {});
        this.onError = onError || ((e) => console.error('[wa-call]', e));
        this.pc = null;
        this.localStream = null;
        this.remoteAudio = null;
        this.callId = null;
        this.state = 'idle'; // idle | connecting | active | ended
        // Call recording (browser-side): operator calls are browser↔Meta
        // direct, so the server never sees the audio — we mix mic + remote
        // via Web Audio, record with MediaRecorder, and upload on hang-up.
        this._recorder = null;
        this._recChunks = [];
        this._recCtx = null;
        this._remoteStream = null;
        // Recording is OPT-IN per call: the operator taps RECORD, which arms
        // this. ontrack only starts the recorder once armed, so an un-recorded
        // call captures nothing (consent + the button's own contract).
        this._recordArmed = false;
        this._recCallId = null;
    }

    /**
     * Operator answers an inbound call.
     * Server gave us the SDP offer via the pending-calls poll.
     */
    async answer({ callId, sdpOffer }) {
        if (!callId) throw new Error('answer() needs callId');
        if (!sdpOffer || sdpOffer.length < 50) throw new Error('Invalid SDP offer — missing v=0 or media line');
        this.callId = callId;
        this._setState('connecting');

        await this._setupPeer();
        await this.pc.setRemoteDescription({ type: 'offer', sdp: normalizeSdp(sdpOffer) });
        // Attach mic BEFORE createAnswer so the answer SDP includes the
        // local audio m-line — without it, Meta sees a recvonly session
        // and the caller hears silence even though we hear them.
        await this._attachLocalAudio();
        this._preferOpus();   // Opus-only — Meta rejects other codecs (see dial()).
        const answer = await this.pc.createAnswer();
        await this.pc.setLocalDescription(answer);
        const sdp = await this._waitForIceComplete();
        if (!sdp || sdp.length < 50) throw new Error('Local SDP empty — ICE gathering failed');

        await api(`/wa-calling/calls/${callId}/accept`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sdp_answer: sdp }),
        });

        this._setState('active');
    }

    /**
     * Operator dials out. Permission must already be granted (server
     * enforces; UI grays the button until /wa-calling/status confirms).
     */
    async dial({ configId, to, contactId = null, conversationId = null }) {
        if (!configId || !to) throw new Error('dial() needs configId and to');
        this._setState('connecting');

        await this._setupPeer();
        await this._attachLocalAudio();
        // Meta's WhatsApp Calling validator accepts ONLY Opus (+ DTMF). The
        // browser's default offer advertises PCMU/PCMA/G722/CN/red too, which
        // Meta rejects with "SDP Validation error". Restrict to Opus before we
        // create the offer so the SDP we send is clean.
        this._preferOpus();
        const offer = await this.pc.createOffer({ offerToReceiveAudio: true });
        await this.pc.setLocalDescription(offer);
        const sdp = await this._waitForIceComplete();

        const r = await api('/wa-calling/calls/dial', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                config_id: configId,
                to,
                sdp_offer: sdp,
                contact_id: contactId,
                conversation_id: conversationId,
            }),
        });
        this.callId = r.call_id;
        // Meta is now ringing the customer. When they pick up, Meta
        // delivers its SDP *answer* to our offer via the call.connect
        // webhook → the server stamps status='active' and stashes the
        // answer. We poll /answer, apply it with setRemoteDescription,
        // and only THEN are we truly connected with two-way audio.
        // Without applying the answer the peer stays in have-local-offer
        // and no media ever flows (the old "connected but silent" bug).
        this._setState('ringing');
        await this._waitForAnswer();
    }

    /**
     * Outbound: poll the server for Meta's SDP answer, apply it, then go
     * active. Resolves once audio is bridged. Throws (and tears down) if
     * the customer never answers or declines before connecting.
     */
    async _waitForAnswer() {
        const RING_MS = 60000;                 // Meta rings up to ~60s
        const deadline = Date.now() + RING_MS;
        while (Date.now() < deadline) {
            if (!this.pc) return;              // operator cancelled / torn down
            let p = null;
            try {
                p = await api(`/wa-calling/calls/${this.callId}/answer`);
            } catch (_) {
                // Transient network/poll error — keep trying.
            }
            if (p) {
                if (p.ended) {
                    this._setState('ended');
                    this._teardown(false);
                    throw new Error(this._endReasonText(p.end_reason) || 'Call ended before it connected.');
                }
                if (p.sdp_answer && p.sdp_answer.length > 50) {
                    try {
                        await this.pc.setRemoteDescription({ type: 'answer', sdp: normalizeSdp(p.sdp_answer, { coerceActiveSetup: true }) });
                    } catch (e) {
                        // Log the raw SDP so a residual parse issue can be
                        // inspected — Meta's calling SDP is non-standard.
                        console.error('[wa-call] setRemoteDescription(answer) failed:', e.message, '\nRaw SDP:\n', p.sdp_answer);
                        this._setState('ended');
                        this._teardown(false);
                        throw new Error('Failed to apply WhatsApp answer: ' + e.message);
                    }
                    this._setState('active');
                    this._watchForRemoteEnd();   // fire-and-forget hang-up watcher
                    return;
                }
            }
            await this._sleep(1200);
        }
        // Ring window elapsed with no pickup — terminate + tear down.
        try { await this.hangup(); } catch (_) {}
        this._setState('ended');
        this._teardown(false);
        throw new Error('No answer — the customer did not pick up.');
    }

    /**
     * Backstop for a remote hang-up. Once media is up, a customer ending
     * the call normally trips onconnectionstatechange → 'disconnected'.
     * But if ICE lingers, Meta's call.terminate webhook still flips the
     * row to 'ended' — this light poll catches that and closes the panel
     * so the operator isn't stuck staring at a dead call. Self-terminates
     * as soon as the peer is torn down.
     */
    async _watchForRemoteEnd() {
        while (this.pc && this.state === 'active') {
            await this._sleep(3000);
            if (!this.pc || this.state !== 'active') return;
            let p = null;
            try { p = await api(`/wa-calling/calls/${this.callId}/answer`); }
            catch (_) { continue; }
            if (p?.ended) {
                this._setState('ended');
                this._teardown(false);
                return;
            }
        }
    }

    _endReasonText(reason) {
        const map = {
            REJECTED: 'The customer declined the call.',
            BUSY: 'The customer is on another call.',
            NO_ANSWER: 'No answer — the customer did not pick up.',
            MISSED: 'The customer missed the call.',
            CANCELLED: 'The call was cancelled.',
        };
        return map[(reason || '').toUpperCase()] || '';
    }

    _sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    async hangup() {
        try {
            if (this.callId && (this.state === 'active' || this.state === 'connecting' || this.state === 'ringing')) {
                await api(`/wa-calling/calls/${this.callId}/terminate`, { method: 'POST' });
            }
        } catch (e) {
            // Don't block tear-down on a missed terminate; Meta's
            // call.terminate webhook will reconcile.
            console.warn('[wa-call] terminate POST failed', e);
        }
        this._teardown();
    }

    /* ───────────────────────── internals ───────────────────────── */

    async _setupPeer() {
        this.pc = new RTCPeerConnection({ iceServers: iceServers() });
        this.pc.ontrack = (ev) => {
            const stream = ev.streams?.[0];
            if (!stream) return;
            if (!this.remoteAudio) {
                this.remoteAudio = document.createElement('audio');
                this.remoteAudio.autoplay = true;
                this.remoteAudio.playsInline = true;
                this.remoteAudio.style.display = 'none';
                document.body.appendChild(this.remoteAudio);
            }
            this.remoteAudio.srcObject = stream;
            this._remoteStream = stream;
            // Autoplay can still be blocked by the browser even after the
            // click that started the call — which shows up as "their voice
            // doesn't come through". Call play() explicitly and surface a
            // block so it's diagnosable rather than silently one-way.
            const pr = this.remoteAudio.play();
            if (pr && typeof pr.catch === 'function') {
                pr.catch((e) => console.warn('[wa-call] remote audio play() blocked:', e?.message));
            }
            console.debug('[wa-call] remote audio track attached');
            // Both legs present now — begin recording the mixed audio, but ONLY
            // if the operator armed it via the RECORD button (opt-in per call).
            if (this._recordArmed) this._startRecording();
        };
        this.pc.oniceconnectionstatechange = () => {
            // ICE reaching 'connected'/'completed' means a media path exists;
            // 'failed' means no path (needs TURN). Logged so a silent call
            // can be diagnosed as ICE vs DTLS vs codec.
            console.debug('[wa-call] iceConnectionState:', this.pc?.iceConnectionState);
        };
        this.pc.onconnectionstatechange = () => {
            const s = this.pc?.connectionState;
            console.debug('[wa-call] connectionState:', s);
            if (s === 'failed' || s === 'disconnected' || s === 'closed') {
                this._setState('ended');
                this._teardown(false);
            }
        };
    }

    async _attachLocalAudio() {
        // Operators MUST grant mic access — without a sendrecv audio
        // track the SDP answer goes out as recvonly and Meta won't
        // bridge our outbound audio. We surface the failure with a
        // clear error so the operator can re-prompt the permission
        // instead of joining a one-way call.
        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
                video: false,
            });
            this.localStream.getTracks().forEach((t) => this.pc.addTrack(t, this.localStream));
        } catch (e) {
            const err = new Error('Microphone access blocked — the caller will not hear you. Grant mic permission in your browser and retry.');
            this.onError(err);
            // Re-throw so answer() / dial() abort instead of pushing a
            // one-way call live. Silent calls are worse than declined.
            throw err;
        }
    }

    /**
     * Restrict the audio transceiver to Opus (+ DTMF) via setCodecPreferences,
     * so the SDP we create advertises ONLY codecs Meta accepts. Meta's WhatsApp
     * Calling validator rejects the browser's default multi-codec offer
     * (PCMU/PCMA/G722/CN/red) with "SDP Validation error". Best-effort: on any
     * browser that lacks setCodecPreferences we just skip and send the default.
     */
    _preferOpus() {
        try {
            if (!('getCapabilities' in RTCRtpSender)) return;
            const caps = RTCRtpSender.getCapabilities('audio');
            if (!caps || !Array.isArray(caps.codecs)) return;
            const keep = caps.codecs.filter((c) =>
                /audio\/opus/i.test(c.mimeType) || /telephone-event/i.test(c.mimeType));
            if (!keep.length) return;
            for (const tx of this.pc.getTransceivers()) {
                const kind = tx.receiver?.track?.kind || tx.sender?.track?.kind || (tx.mid ? 'audio' : null);
                if (typeof tx.setCodecPreferences === 'function' && kind === 'audio') {
                    try { tx.setCodecPreferences(keep); } catch (e) { /* codec set unsupported on this tx */ }
                }
            }
        } catch (e) {
            console.warn('[wa-call] preferOpus failed (sending default SDP):', e);
        }
    }

    /**
     * Non-trickle ICE: wait for all candidates to gather, then return
     * the complete SDP. Simpler than trickle + fits the one-shot
     * action-endpoint design.
     */
    async _waitForIceComplete() {
        if (this.pc.iceGatheringState === 'complete') {
            return this.pc.localDescription.sdp;
        }
        return new Promise((resolve) => {
            const onChange = () => {
                if (this.pc.iceGatheringState === 'complete') {
                    this.pc.removeEventListener('icegatheringstatechange', onChange);
                    resolve(this.pc.localDescription.sdp);
                }
            };
            this.pc.addEventListener('icegatheringstatechange', onChange);
            // Safety timer — some browsers stall ICE on networks
            // without external connectivity; cap at 3s and ship what
            // we have. Meta retries failed accept with NO_ANSWER so a
            // late-arriving candidate isn't fatal.
            setTimeout(() => resolve(this.pc.localDescription.sdp), 3000);
        });
    }

    _setState(s) {
        this.state = s;
        try { this.onState(s); } catch (_) {}
    }

    /**
     * Operator opt-in: arm + begin recording THIS call. Called from the
     * RECORD button. Operator calls are browser↔Meta direct, so recording is
     * browser-side (there is NO server/Node recorder for these — that path is
     * only for AI-bridge calls, which is why the old button 404'd). If the
     * remote leg is already attached we start immediately; otherwise ontrack
     * starts it the moment the customer's audio arrives. The mix is uploaded
     * to /wa-calling/calls/{id}/recording on hang-up. Returns false only when
     * the browser can't record at all (no MediaRecorder).
     */
    startRecording() {
        if (typeof MediaRecorder === 'undefined') return false;
        this._recordArmed = true;
        if (this._remoteStream) this._startRecording();
        return true;
    }

    /**
     * Start recording the call. Operator calls run browser↔Meta directly,
     * so there's no server-side recorder — we mix the local mic and the
     * remote (customer) audio into one stream via Web Audio and capture it
     * with MediaRecorder. Best-effort: any unsupported API just skips.
     */
    _startRecording() {
        try {
            if (this._recorder) return;                       // already recording
            if (typeof MediaRecorder === 'undefined') return; // unsupported browser
            if (!this.localStream || !this._remoteStream) return;
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;

            this._recCtx = new AC();
            // The context is often created in 'suspended' state (it's built
            // after async ICE/track work, not directly in the click handler).
            // A suspended context feeds ZERO samples to the destination, so the
            // recording comes out empty ("I talked but nothing happened").
            // Resume it so real audio flows into the recorder.
            if (this._recCtx.state === 'suspended') {
                this._recCtx.resume().catch((e) => console.warn('[wa-call] audioCtx resume failed:', e?.message));
            }
            const dest = this._recCtx.createMediaStreamDestination();
            try { this._recCtx.createMediaStreamSource(this.localStream).connect(dest); } catch (_) {}
            try { this._recCtx.createMediaStreamSource(this._remoteStream).connect(dest); } catch (_) {}

            const mime = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus']
                .find((m) => MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(m));
            this._recChunks = [];
            this._recCallId = this.callId;
            this._recorder = mime
                ? new MediaRecorder(dest.stream, { mimeType: mime })
                : new MediaRecorder(dest.stream);
            this._recorder.ondataavailable = (e) => {
                if (e.data && e.data.size) this._recChunks.push(e.data);
            };
            this._recorder.start(1000); // flush a chunk every second
            console.debug('[wa-call] recording started', { mime: mime || 'default', ctx: this._recCtx.state });
        } catch (e) {
            console.warn('[wa-call] recording start failed:', e?.message);
        }
    }

    /**
     * Stop recording and POST the mixed audio to the server, which attaches
     * it to the call-log row so it plays back under /call-logs. Captures the
     * call id + chunks locally because _teardown nulls the instance fields
     * right after this returns.
     */
    _stopRecordingAndUpload() {
        const rec = this._recorder;
        const ctx = this._recCtx;
        const callId = this._recCallId;
        this._recorder = null;
        this._recCtx = null;
        this._recCallId = null;
        // Do NOT reset this._recChunks yet: rec.stop() fires one final
        // ondataavailable that appends the last buffered audio to it, and
        // finish() (in onstop) reads it AFTER that flush. Clearing it here
        // would drop the tail of every call — and empty short calls entirely.
        if (!rec) { try { ctx?.close(); } catch (_) {} return; }

        const finish = () => {
            try { ctx?.close(); } catch (_) {}
            const chunks = this._recChunks || [];
            this._recChunks = [];
            if (!callId || !chunks.length) {
                console.warn('[wa-call] no recording captured (no chunks)');
                return;
            }
            const blob = new Blob(chunks, { type: chunks[0].type || 'audio/webm' });
            console.debug('[wa-call] recording stopped, size:', blob.size);
            if (blob.size < 256) return; // genuinely empty — nothing to save
            const fd = new FormData();
            fd.append('recording', blob, `call-${callId}.webm`);
            fetch(`/wa-calling/calls/${callId}/recording`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            })
                .then((r) => r.json().catch(() => ({})))
                .then((d) => {
                    if (d && d.ok) {
                        console.debug('[wa-call] recording uploaded:', d.url);
                        window.WaToaster?.success?.('Call recording saved to Call logs.');
                    } else {
                        console.warn('[wa-call] recording upload rejected:', d && d.error);
                    }
                })
                .catch((e) => console.warn('[wa-call] recording upload failed:', e?.message));
        };

        try {
            if (rec.state !== 'inactive') {
                rec.onstop = finish;   // stop flushes the final chunk, then fires onstop
                rec.stop();
            } else {
                finish();
            }
        } catch (e) {
            console.warn('[wa-call] recording stop failed:', e?.message);
            try { ctx?.close(); } catch (_) {}
        }
    }

    _teardown(setIdle = true) {
        // Flush + upload the recording BEFORE we stop the mic tracks, so the
        // final audio frames aren't lost.
        this._stopRecordingAndUpload();
        try { this.pc?.getSenders().forEach((s) => s.track?.stop()); } catch (_) {}
        try { this.localStream?.getTracks().forEach((t) => t.stop()); } catch (_) {}
        try { this.pc?.close(); } catch (_) {}
        if (this.remoteAudio?.parentNode) this.remoteAudio.remove();
        this.pc = null;
        this.localStream = null;
        this.remoteAudio = null;
        this.callId = null;
        if (setIdle) this._setState('idle');
    }
}

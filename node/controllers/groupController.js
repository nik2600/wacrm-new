// controllers/groupController.js
// ==============================
// WhatsApp Group operations — Baileys passthrough.
//
// Every endpoint follows the same shape the rest of the Node controllers
// use:  /api/groups/<verb>/:senderPhone   (X-Node-Token auth in production).
// The sock is resolved from `app.locals.clients[senderPhoneNumber]` —
// same pattern messageController uses. Baileys is the ONLY engine that
// supports group creation + membership management (the Meta Cloud API
// has no group-create endpoint, Twilio has no group concept at all),
// so this controller returns 501 when called against a non-Baileys
// sender.
//
// Every method ACK is JSON: { ok: boolean, ...payload, error?: string }.
// ==============================

/**
 * Resolve the Baileys sock for the given sender phone. Returns null
 * when the device isn't paired / connected — caller surfaces a 503.
 */
function _getSock(app, senderPhoneNumber) {
  const phone = String(senderPhoneNumber || '').replace(/\D+/g, '');
  if (!phone) return null;
  if (!app.locals.client_ready?.[phone]) return null;
  return app.locals.clients?.[phone] || null;
}

/**
 * Strict group-JID regex: digits + optional `-digits` followed by `@g.us`.
 * Mirrors the Laravel-side `normaliseGroupJid` check so neither end
 * accepts an XSS / SQLi / path-traversal jid the operator forged.
 */
function _isValidGroupJid(raw) {
  return typeof raw === 'string' && /^\d+(?:-\d+)?@g\.us$/.test(raw);
}

/**
 * Recently-left ledger keyed by "<workspaceId>::<jid>". groupLeave
 * writes a MONOTONIC timestamp; groupListAll filters out jids leave'd
 * within the last 30s so the immediate GET /groups after POST /leave
 * never returns the left group from Baileys' stale participating
 * roster.
 *
 *   - Keyed by workspace_id (not senderPhone) so two tenants that
 *     happen to re-pair the same phone digits can't pollute each
 *     other's view. Laravel passes the workspace via the bridge body.
 *   - Monotonic clock (process.hrtime.bigint) so a wall-clock step
 *     backward (NTP, DST, leap second) doesn't strand entries with a
 *     negative delta hiding groups forever.
 *   - Background sweeper drops expired entries every 60s; we also
 *     opportunistically sweep on every 32nd write so the map can't
 *     grow unbounded if the timer is delayed by a busy event loop.
 */
const _recentlyLeft = new Map();
const _LEFT_TTL_NS = 30n * 1_000_000_000n; // 30s in nanoseconds
let _leftWriteCount = 0;

function _monoNow() {
  return process.hrtime.bigint();
}

function _sweepRecentlyLeft() {
  const now = _monoNow();
  for (const [k, ts] of _recentlyLeft) {
    if (now - ts > _LEFT_TTL_NS) _recentlyLeft.delete(k);
  }
}
// unref()'d so the timer doesn't keep the Node event loop alive on shutdown.
setInterval(_sweepRecentlyLeft, 60_000).unref();

function _markLeft(workspaceId, jid) {
  if (!workspaceId || !jid) return;
  _recentlyLeft.set(`${workspaceId}::${jid}`, _monoNow());
  if (((++_leftWriteCount) & 31) === 0) _sweepRecentlyLeft();
}
function _isRecentlyLeft(workspaceId, jid) {
  if (!workspaceId) return false;
  const k  = `${workspaceId}::${jid}`;
  const ts = _recentlyLeft.get(k);
  if (!ts) return false;
  if (_monoNow() - ts > _LEFT_TTL_NS) {
    _recentlyLeft.delete(k);
    return false;
  }
  return true;
}

/**
 * Re-fetch groupMetadata with a bounded retry. Baileys' groupCreate
 * returns synchronously but a follow-up groupMetadata() on the just-
 * created jid can transiently come back empty while the server
 * settles the iq response. 3 × 300ms gives meta time to populate
 * without blocking the request more than ~1s worst-case.
 *
 * Distinguishes TRANSIENT failures (empty meta) from TERMINAL ones
 * (not-authorized / item-not-found / forbidden — the device was
 * kicked, or the jid was never a group we belong to). Terminal
 * errors short-circuit so we don't burn ~900ms on retries that will
 * always reproduce.
 *
 * Also accepts a freshly-created 1-participant group as a valid meta
 * (length===1 is fine — a group can legally start with just the
 * creator until adds settle). The OLD `length > 0` gate added 600ms
 * of latency to every just-created group.
 */
async function _freshMeta(sock, jid, { tries = 3, gapMs = 300 } = {}) {
  const TERMINAL = /not[-_]authorized|item[-_]not[-_]found|forbidden|404|410/i;
  for (let i = 0; i < tries; i++) {
    try {
      const m = await sock.groupMetadata(jid);
      if (m && Array.isArray(m.participants)) return m;
    } catch (e) {
      const msg = String(e?.message || e?.data?.reason || '');
      if (TERMINAL.test(msg)) return null;
      // else: transient — retry below
    }
    if (i < tries - 1) await new Promise((r) => setTimeout(r, gapMs));
  }
  return null;
}

/**
 * Coerce a phone-or-JID input into a WhatsApp user JID.
 *   "919999999999"        → "919999999999@s.whatsapp.net"
 *   "919999999999@..."    → returned unchanged
 *   ""/null               → returns null (caller filters)
 */
function _toUserJid(raw) {
  const s = String(raw || '').trim();
  if (!s) return null;
  if (s.includes('@')) return s;
  const d = s.replace(/\D+/g, '');
  return d ? `${d}@s.whatsapp.net` : null;
}

/**
 * POST /api/groups/create/:phoneNumber
 * Body: { subject: string, participants: string[] }
 *
 * Creates a new WhatsApp group with the sender + the given participants
 * (numbers OR JIDs). Returns the new group's JID + metadata so the
 * caller can immediately route follow-up sends to it.
 */
export async function groupCreate(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const subject = String(req.body?.subject || req.body?.name || '').trim();
    const rawParticipants = Array.isArray(req.body?.participants) ? req.body.participants : [];
    if (!subject) return res.status(422).json({ ok: false, error: 'subject is required.' });
    if (rawParticipants.length === 0) return res.status(422).json({ ok: false, error: 'participants is required (at least 1).' });

    const participants = rawParticipants.map(_toUserJid).filter(Boolean);
    if (participants.length === 0) return res.status(422).json({ ok: false, error: 'No valid participant phones supplied.' });

    console.log(`[GROUP] CREATE subject="${subject}" with ${participants.length} participants by ${senderPhone}`);
    const created = await sock.groupCreate(subject, participants);
    const jid = created?.id || created?.gid?._serialized || created?.gid;
    if (!jid) return res.status(502).json({ ok: false, error: 'Group create returned no JID.', raw: created });

    // Resolve meta with retry — Baileys' groupCreate returns the
    // metadata directly in `created`, but a follow-up groupMetadata()
    // can transiently come back empty/partial on the first try while
    // the server settles. `_freshMeta` retries 3×300ms.
    const meta = await _freshMeta(sock, jid);

    // participants_count: pick the FIRST source with a non-empty array.
    // The previous `(meta?.participants || created?.participants || participants).length`
    // chain treated an empty array as truthy and reported 0 participants
    // when meta returned `{participants:[]}`. Explicit length checks fix it.
    const metaPart    = Array.isArray(meta?.participants)    && meta.participants.length    ? meta.participants    : null;
    const createdPart = Array.isArray(created?.participants) && created.participants.length ? created.participants : null;
    const list = metaPart || createdPart || participants;

    return res.json({
      ok: true,
      jid,
      subject,
      participants_count: list.length,
      meta: meta || created || null,
    });
  } catch (e) {
    console.error(`[GROUP] create failed: ${e?.message}`);
    return res.status(500).json({ ok: false, error: e?.message || 'group create failed' });
  }
}

/**
 * GET /api/groups/all/:phoneNumber
 * Returns every group the sender is currently a participant in.
 */
export async function groupListAll(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    // Workspace id comes from Laravel as a query param (?workspace_id=)
    // so the recently-left ledger key is tenant-scoped — see comment on
    // _recentlyLeft. Falling back to senderPhone for legacy callers
    // would have re-introduced the cross-tenant pollution we just fixed,
    // so we DON'T fall back: if no workspace_id, the suppression simply
    // doesn't apply (no cache hit possible).
    const workspaceId = String(req.query?.workspace_id || req.body?.workspace_id || '');
    const all = await sock.groupFetchAllParticipating();
    // groupFetchAllParticipating returns a dict { jid: meta, ... } — flatten
    // it into an array for the API caller and trim each row to the fields
    // the app needs. Filter out any jids the caller has JUST left (Baileys'
    // server-side participating roster may still include them for a few
    // seconds after `groupLeave` while the notify propagates).
    const rows = Object.values(all || {})
      .filter((g) => !_isRecentlyLeft(workspaceId, g.id))
      .map((g) => ({
        jid:                 g.id,
        subject:             g.subject || '',
        owner:               g.owner || null,
        creation:            g.creation || null,
        description:         g.desc || null,
        participants_count:  Array.isArray(g.participants) ? g.participants.length : 0,
        announce_only:       !!g.announce,
        restrict:            !!g.restrict,
        ephemeral_duration:  g.ephemeralDuration || 0,
      }));
    return res.json({ ok: true, groups: rows, total: rows.length });
  } catch (e) {
    console.error(`[GROUP] list-all failed: ${e?.message}`);
    return res.status(500).json({ ok: false, error: e?.message || 'group list failed' });
  }
}

/**
 * GET /api/groups/meta/:phoneNumber?jid=<group_jid>
 * Returns full metadata for a single group (subject, description,
 * participants list with admin flags, settings).
 */
export async function groupMetadata(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const jid = String(req.query?.jid || req.body?.jid || '').trim();
    if (!_isValidGroupJid(jid)) return res.status(422).json({ ok: false, error: 'jid must match <digits>(-<digits>)?@g.us' });

    const meta = await sock.groupMetadata(jid);
    return res.json({ ok: true, meta });
  } catch (e) {
    return res.status(500).json({ ok: false, error: e?.message || 'group meta failed' });
  }
}

/**
 * POST /api/groups/participants/:phoneNumber
 * Body: { jid, action: 'add'|'remove'|'promote'|'demote', participants: string[] }
 *
 * Same call pattern as sock.groupParticipantsUpdate. Each entry in the
 * participants array can be a phone or a JID; we normalise to JID.
 */
export async function groupParticipantsUpdate(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const jid    = String(req.body?.jid || '').trim();
    const action = String(req.body?.action || '').toLowerCase();
    const raw    = Array.isArray(req.body?.participants) ? req.body.participants : [];
    if (!_isValidGroupJid(jid)) return res.status(422).json({ ok: false, error: 'jid must match <digits>(-<digits>)?@g.us' });
    if (!['add', 'remove', 'promote', 'demote'].includes(action)) {
      return res.status(422).json({ ok: false, error: 'action must be add|remove|promote|demote' });
    }
    const participants = raw.map(_toUserJid).filter(Boolean);
    if (participants.length === 0) return res.status(422).json({ ok: false, error: 'participants is required.' });

    console.log(`[GROUP] ${action.toUpperCase()} jid=${jid} count=${participants.length}`);
    const result = await sock.groupParticipantsUpdate(jid, participants, action);
    // Baileys returns [{ status: '200', jid }, ...] — '200' = ok, anything
    // else (403 not-allowed, 404 not-on-whatsapp, 408 timeout, 409 already-
    // a-member, etc.) means that participant failed. Surface that:
    //   - ok      = every entry succeeded
    //   - partial = some 200, some failures → ok=true but partial=true
    //   - failed  = every entry failed → ok=false
    // The Laravel side passes `result` through so the app can render
    // per-participant errors instead of a misleading "added" toast.
    const okRows     = Array.isArray(result) ? result.filter((r) => String(r?.status || '') === '200').length : 0;
    const totalRows  = Array.isArray(result) ? result.length : 0;
    const allFailed  = totalRows > 0 && okRows === 0;
    const partial    = totalRows > 0 && okRows > 0 && okRows < totalRows;

    // Re-fetch metadata for the authoritative post-mutation roster (skip
    // the re-fetch on all-failed since nothing changed).
    const meta = allFailed ? null : await _freshMeta(sock, jid);

    return res.json({
      ok:      !allFailed,
      action,
      result,
      meta,
      partial,
      ok_count:     okRows,
      fail_count:   totalRows - okRows,
      total_count:  totalRows,
    });
  } catch (e) {
    console.error(`[GROUP] participants update failed: ${e?.message}`);
    return res.status(500).json({ ok: false, error: e?.message || 'group participants update failed' });
  }
}

/**
 * POST /api/groups/subject/:phoneNumber
 * Body: { jid, subject }
 */
export async function groupUpdateSubject(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const jid     = String(req.body?.jid || '').trim();
    const subject = String(req.body?.subject || '').trim();
    if (!_isValidGroupJid(jid)) return res.status(422).json({ ok: false, error: 'jid must match <digits>(-<digits>)?@g.us' });
    if (!subject)               return res.status(422).json({ ok: false, error: 'subject is required.' });

    await sock.groupUpdateSubject(jid, subject);
    return res.json({ ok: true, jid, subject });
  } catch (e) {
    return res.status(500).json({ ok: false, error: e?.message || 'group subject update failed' });
  }
}

/**
 * POST /api/groups/description/:phoneNumber
 * Body: { jid, description }
 */
export async function groupUpdateDescription(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const jid         = String(req.body?.jid || '').trim();
    const description = String(req.body?.description || '');
    if (!_isValidGroupJid(jid)) return res.status(422).json({ ok: false, error: 'jid must match <digits>(-<digits>)?@g.us' });

    await sock.groupUpdateDescription(jid, description);
    return res.json({ ok: true, jid, description });
  } catch (e) {
    return res.status(500).json({ ok: false, error: e?.message || 'group description update failed' });
  }
}

/**
 * POST /api/groups/settings/:phoneNumber
 * Body: { jid, setting: 'announcement'|'not_announcement'|'locked'|'unlocked' }
 *
 * Baileys passes these tags verbatim to WhatsApp. 'announcement' = admin-only
 * messaging; 'locked' = admin-only metadata edits.
 */
export async function groupUpdateSetting(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const jid     = String(req.body?.jid || '').trim();
    const setting = String(req.body?.setting || '').toLowerCase();
    if (!_isValidGroupJid(jid)) return res.status(422).json({ ok: false, error: 'jid must match <digits>(-<digits>)?@g.us' });
    if (!['announcement', 'not_announcement', 'locked', 'unlocked'].includes(setting)) {
      return res.status(422).json({ ok: false, error: 'setting must be announcement|not_announcement|locked|unlocked' });
    }

    await sock.groupSettingUpdate(jid, setting);
    return res.json({ ok: true, jid, setting });
  } catch (e) {
    return res.status(500).json({ ok: false, error: e?.message || 'group setting update failed' });
  }
}

/**
 * POST /api/groups/leave/:phoneNumber
 * Body: { jid }
 */
export async function groupLeave(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const jid = String(req.body?.jid || '').trim();
    if (!_isValidGroupJid(jid)) return res.status(422).json({ ok: false, error: 'jid must match <digits>(-<digits>)?@g.us' });

    await sock.groupLeave(jid);
    // Mark the jid so groupListAll filters it out for the next 30s.
    // Keyed by workspace_id (passed by Laravel) so two tenants on the
    // same phone digits can't cross-pollute. Baileys' server-side
    // participating roster can still include the jid for a few seconds
    // after the leave-notify; this suppression bridges that window.
    const workspaceId = String(req.body?.workspace_id || '');
    _markLeft(workspaceId, jid);
    return res.json({ ok: true, jid });
  } catch (e) {
    return res.status(500).json({ ok: false, error: e?.message || 'group leave failed' });
  }
}

/**
 * GET /api/groups/invite-code/:phoneNumber?jid=<group_jid>
 * Returns the join code so the caller can build https://chat.whatsapp.com/<code>.
 */
export async function groupInviteCode(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const jid = String(req.query?.jid || req.body?.jid || '').trim();
    if (!_isValidGroupJid(jid)) return res.status(422).json({ ok: false, error: 'jid must match <digits>(-<digits>)?@g.us' });

    const code = await sock.groupInviteCode(jid);
    return res.json({ ok: true, jid, code, url: `https://chat.whatsapp.com/${code}` });
  } catch (e) {
    return res.status(500).json({ ok: false, error: e?.message || 'group invite-code failed' });
  }
}

/**
 * POST /api/groups/revoke-invite/:phoneNumber
 * Body: { jid }
 *
 * Rotates the invite code so the previous link stops working.
 */
export async function groupRevokeInvite(req, res, app) {
  const senderPhone = req.params.phoneNumber;
  try {
    const sock = _getSock(app, senderPhone);
    if (!sock) return res.status(503).json({ ok: false, error: 'Sender device is not connected.' });

    const jid = String(req.body?.jid || '').trim();
    if (!_isValidGroupJid(jid)) return res.status(422).json({ ok: false, error: 'jid must match <digits>(-<digits>)?@g.us' });

    const code = await sock.groupRevokeInvite(jid);
    return res.json({ ok: true, jid, code, url: `https://chat.whatsapp.com/${code}` });
  } catch (e) {
    return res.status(500).json({ ok: false, error: e?.message || 'group revoke-invite failed' });
  }
}

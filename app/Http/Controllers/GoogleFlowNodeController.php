<?php

namespace App\Http\Controllers;

use App\Models\GoogleFormSession;
use App\Models\Workspace;
use App\Services\Google\GoogleApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Endpoints that back the Google Sheets / Docs / Forms flow nodes.
 *
 * Two auth gates:
 *   - Node-runtime calls (X-Node-Token shared secret):
 *       POST /api/flow-node/google/sheet-write
 *       POST /api/flow-node/google/sheet-read
 *       POST /api/flow-node/google/doc-generate
 *       POST /api/flow-node/google/form-send       (pause + register session)
 *
 *   - Google Apps Script webhook (per-row webhook_token):
 *       POST /api/google/form-response             (resume the flow)
 *
 *   - Logged-in user (session auth) — UI helpers for the flow builder:
 *       GET  /api/google/picker/sheets|docs|forms
 *       GET  /google-account/forms/{formId}/apps-script.gs
 */
class GoogleFlowNodeController extends Controller
{
    private const TOKEN_HEADER = 'X-Node-Token';

    public function __construct(private readonly GoogleApiService $g) {}

    // ────────────── Node → Laravel: Sheets / Docs / Forms ──────────────

    /** POST /api/flow-node/google/sheet-write */
    public function sheetWrite(Request $request): JsonResponse
    {
        if (!$this->authedNode($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        if ($gated = $this->integrationDisabledOrNull()) return $gated;
        $data = $request->validate([
            'workspace_id' => 'required|integer',
            'sheet_id'     => 'required|string|max:128',
            'tab_name'     => 'nullable|string|max:64',
            'values'       => 'required|array',
            'values.*'     => 'nullable',
            // Opt-in header mapping (Sheets WRITE node). column_headers[] is the
            // label per value, index-aligned to values[]; match_by_header ON
            // places each value under the sheet column with that header instead
            // of appending left-to-right. Absent/false → legacy positional.
            'column_headers'   => 'nullable|array',
            'column_headers.*' => 'nullable|string|max:128',
            'match_by_header'  => 'nullable|boolean',
        ]);
        $workspace = Workspace::find($data['workspace_id']);
        if (!$workspace) return response()->json(['ok' => false, 'error' => 'workspace_not_found'], 404);

        $tabName = (string) ($data['tab_name'] ?? '');
        $values  = array_map(fn ($v) => (string) ($v ?? ''), $data['values']);

        // Header-matched mode: reposition each value under its named sheet column.
        // Falls back to the positional $values when the sheet has no header row
        // (empty sheet) so a fresh sheet still works.
        if (!empty($data['match_by_header']) && !empty($data['column_headers'])) {
            $mapped = $this->headerMatchedRow(
                $workspace, $data['sheet_id'], $tabName,
                array_map('strval', $data['column_headers']), $values,
            );
            if ($mapped !== null) $values = $mapped;
        }

        $res = $this->g->appendSheetRow($workspace, $data['sheet_id'], $tabName, $values);
        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    /**
     * Position row values under the sheet columns whose HEADER matches each
     * column's label (case-insensitive, trimmed). A label the sheet doesn't
     * have is appended after the last sheet column so nothing is silently
     * dropped. Returns null when the sheet has no header row (caller then
     * appends positionally).
     *
     * @param  string[] $labels  header label per value (index-aligned to $values)
     * @param  string[] $values
     * @return string[]|null
     */
    private function headerMatchedRow(Workspace $workspace, string $sheetId, string $tabName, array $labels, array $values): ?array
    {
        $hdr = $this->g->readSheetHeaders($workspace, $sheetId, $tabName);
        $sheetHeaders = ($hdr['ok'] ?? false) ? (array) ($hdr['headers'] ?? []) : [];
        if (count($sheetHeaders) === 0) return null;   // no header row → positional

        $norm = fn ($s) => mb_strtolower(trim((string) $s));
        $index = [];
        foreach ($sheetHeaders as $i => $h) {
            $key = $norm($h);
            if ($key !== '' && !array_key_exists($key, $index)) $index[$key] = (int) $i;
        }

        $row   = array_fill(0, count($sheetHeaders), '');
        $extra = [];   // labels not present in the sheet → appended at the end
        foreach ($labels as $j => $label) {
            $val = (string) ($values[$j] ?? '');
            $key = $norm($label);
            if ($key !== '' && array_key_exists($key, $index)) {
                $row[$index[$key]] = $val;
            } elseif ($val !== '' || trim((string) $label) !== '') {
                $extra[] = $val;
            }
        }
        return array_merge($row, $extra);
    }

    /** POST /api/flow-node/google/sheet-read */
    public function sheetRead(Request $request): JsonResponse
    {
        if (!$this->authedNode($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        if ($gated = $this->integrationDisabledOrNull()) return $gated;
        $data = $request->validate([
            'workspace_id'  => 'required|integer',
            'sheet_id'      => 'required|string|max:128',
            'tab_name'      => 'nullable|string|max:64',
            'match_column'  => 'nullable|string|max:64',
            'match_value'   => 'nullable|string|max:255',
            'limit'         => 'nullable|integer|min:1|max:200',
        ]);
        $workspace = Workspace::find($data['workspace_id']);
        if (!$workspace) return response()->json(['ok' => false, 'error' => 'workspace_not_found'], 404);

        $res = $this->g->readSheetRows(
            $workspace,
            $data['sheet_id'],
            (string) ($data['tab_name'] ?? ''),
            $data['match_column'] ?? null,
            $data['match_value']  ?? null,
            (int) ($data['limit'] ?? 1),
        );
        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    /** POST /api/flow-node/google/doc-generate */
    public function docGenerate(Request $request): JsonResponse
    {
        if (!$this->authedNode($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        if ($gated = $this->integrationDisabledOrNull()) return $gated;
        $data = $request->validate([
            'workspace_id'  => 'required|integer',
            'template_id'   => 'required|string|max:128',
            'new_title'     => 'required|string|max:240',
            'vars'          => 'nullable|array',
            'shareable'     => 'nullable|boolean',
        ]);
        $workspace = Workspace::find($data['workspace_id']);
        if (!$workspace) return response()->json(['ok' => false, 'error' => 'workspace_not_found'], 404);

        $res = $this->g->copyDocAndFill(
            $workspace,
            $data['template_id'],
            $data['new_title'],
            (array) ($data['vars'] ?? []),
            (bool) ($data['shareable'] ?? true),
        );
        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    /**
     * POST /api/flow-node/google/form-send
     *
     * Called by Node when a Google Form flow node fires. We mint a
     * google_form_sessions row that maps the customer's flow session →
     * the form. Then we return the form URL to Node so it can ship it
     * to the customer via WhatsApp. When the customer submits and the
     * Apps Script webhook fires, we look up this row to resume.
     */
    public function formSend(Request $request): JsonResponse
    {
        if (!$this->authedNode($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        if ($gated = $this->integrationDisabledOrNull()) return $gated;
        $data = $request->validate([
            'workspace_id'    => 'required|integer',
            'google_form_id'  => 'required|string|max:128',
            'flow_session_id' => 'required|string|max:64',
            'contact_phone'   => 'nullable|string|max:32',
            'save_variable'   => 'nullable|string|max:64',
            'resume_port'     => 'nullable|string|max:32',
            'expires_in_sec'  => 'nullable|integer|min:60|max:604800',
        ]);
        $workspace = Workspace::find($data['workspace_id']);
        if (!$workspace) return response()->json(['ok' => false, 'error' => 'workspace_not_found'], 404);

        $formInfo = $this->g->getForm($workspace, $data['google_form_id']);
        if (!$formInfo['ok']) {
            return response()->json([
                'ok' => false, 'error' => 'form_unreachable',
                'message' => $formInfo['error'] ?? 'Could not load form metadata',
            ], 422);
        }
        $responderUri = (string) ($formInfo['form']['responderUri'] ?? '');
        if ($responderUri === '') {
            return response()->json(['ok' => false, 'error' => 'no_responder_uri'], 422);
        }

        // Garbage-collect stale rows for the same (form, session) so
        // we never resume into the wrong flow when the same contact is
        // re-routed through the node.
        GoogleFormSession::where('flow_session_id', $data['flow_session_id'])
            ->where('google_form_id', $data['google_form_id'])
            ->whereNull('submitted_at')
            ->delete();

        $row = GoogleFormSession::create([
            'workspace_id'     => $workspace->id,
            'flow_session_id'  => $data['flow_session_id'],
            'google_form_id'   => $data['google_form_id'],
            'contact_phone'    => preg_replace('/\D+/', '', (string) ($data['contact_phone'] ?? '')) ?: null,
            'save_variable'    => $data['save_variable']  ?? 'form_response',
            'resume_port'      => $data['resume_port']    ?? 'submitted',
            'webhook_token'    => Str::random(40),
            'expires_at'       => now()->addSeconds((int) ($data['expires_in_sec'] ?? 86400)),
        ]);

        return response()->json([
            'ok'             => true,
            'form_url'       => $responderUri,
            'webhook_token'  => $row->webhook_token,
            'webhook_url'    => url('/api/google/form-response'),
            'apps_script_url'=> url("/google-account/forms/{$data['google_form_id']}/apps-script.gs"),
            'session_row_id' => $row->id,
        ]);
    }

    // ──────────── Apps Script → Laravel: form submission webhook ─────────

    /**
     * POST /api/google/form-response
     *
     * Apps Script the customer pasted into their form fires this on every
     * submission. We:
     *   1. Find the google_form_sessions row by form_id + (optionally) the
     *      respondent email, picking the most-recently created unsubmitted
     *      row.
     *   2. Write the answers into the matched flow session.
     *   3. Resume the flow on the configured port (default "submitted").
     *
     * Auth: X-Workspace-Token header. Multiple workspaces can connect
     * the same form (rare but supported) — we accept the first row whose
     * webhook_token matches anything still active.
     */
    public function formResponse(Request $request): JsonResponse
    {
        $given = (string) $request->header('X-Workspace-Token', '');
        // We accept the workspace-level Node token *or* a per-row
        // webhook_token depending on where the script gets the value
        // baked in (the generator uses the per-row token).
        $payload = $request->validate([
            'form_id'      => 'required|string|max:128',
            'response_id'  => 'nullable|string|max:128',
            'respondent'   => 'nullable|string|max:255',
            'submitted_at' => 'nullable|string|max:64',
            'answers'      => 'required|array',
        ]);

        $row = GoogleFormSession::query()
            ->where('webhook_token', $given)
            ->where('google_form_id', $payload['form_id'])
            ->whereNull('submitted_at')
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            // Fallback: any unsubmitted row for this form, restricted by
            // workspace's Node token. This covers operators who edited
            // the generated script and replaced the per-row token with
            // their workspace token.
            $expected = node_token();
            if ($expected !== '' && hash_equals($expected, $given)) {
                $row = GoogleFormSession::query()
                    ->where('google_form_id', $payload['form_id'])
                    ->whereNull('submitted_at')
                    ->orderByDesc('id')
                    ->first();
            }
        }

        if (!$row) {
            // No paused session — log + return 200 so Apps Script doesn't
            // keep retrying. The submission just gets logged in Google
            // Forms like normal; it just doesn't resume any flow.
            Log::info('[GFlowNode] form-response with no matching session form_id=' . $payload['form_id']);
            return response()->json(['ok' => true, 'matched' => false]);
        }

        if ($row->expires_at && Carbon::parse($row->expires_at)->isPast()) {
            $row->update(['submitted_at' => now()]); // mark consumed even on expiry
            return response()->json(['ok' => true, 'matched' => false, 'reason' => 'expired']);
        }

        DB::transaction(function () use ($row, $payload) {
            $row->update([
                'answers_json' => $payload['answers'],
                'submitted_at' => now(),
            ]);
        });

        // Hand off to Node to resume the flow. Node owns the in-memory
        // flow_sessions table, so we POST instead of touching DB state.
        // Resolve the Node URL the same way the rest of the platform does:
        // admin-configured SystemSetting first, env SERVER_URL fallback.
        // (NODE_BRIDGE_URL is a one-off from DripEnrollmentService; not the
        // canonical key — using SystemSetting matches WaCampaignsController
        // + WaCallingWebhookController.)
        try {
            $nodeBase = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
            if ($nodeBase === '') {
                Log::warning('[GFlowNode] Node URL not configured (baileys_server_url / SERVER_URL) — flow cannot resume');
            } else {
                $token = node_token();
                \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Node-Token' => $token,
                ])->timeout(10)->post(rtrim($nodeBase, '/') . '/api/flow-resume', [
                    'session_id'    => $row->flow_session_id,
                    'workspace_id'  => $row->workspace_id,
                    'resume_port'   => $row->resume_port,
                    'save_variable' => $row->save_variable,
                    'answers'       => $payload['answers'],
                    'response_id'   => $payload['response_id'] ?? null,
                    'respondent'    => $payload['respondent']  ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[GFlowNode] flow-resume call failed: ' . $e->getMessage());
            // Don't 500 — the row is marked submitted, the operator can
            // see it landed. The flow simply won't advance, which is
            // recoverable on the next inbound message via the session
            // resumer (Question/Buttons style fallback).
        }

        return response()->json(['ok' => true, 'matched' => true, 'session' => $row->id]);
    }

    // ────────────── Builder pickers + Apps Script generator ──────────────

    /** GET /api/google/picker/{kind}  kind ∈ sheets|docs|forms */
    public function picker(Request $request, string $kind): JsonResponse
    {
        $user = Auth::user();
        $workspace = $user ? Workspace::find($user->current_workspace_id) : null;
        if (!$workspace) return response()->json(['ok' => false, 'error' => 'no_workspace'], 401);

        // Master-toggle gate — without this, flow-builder pickers would
        // keep hitting Drive/Sheets/Docs APIs for hours after the admin
        // flipped the integration off (same hours-long leak the node-
        // runtime gate was designed to close).
        if ($gated = $this->integrationDisabledOrNull()) return $gated;

        $files = match ($kind) {
            'sheets' => $this->g->listSheets($workspace),
            'docs'   => $this->g->listDocs($workspace),
            'forms'  => $this->g->listForms($workspace),
            default  => null,
        };
        if ($files === null) return response()->json(['ok' => false, 'error' => 'unknown_kind'], 422);

        return response()->json([
            'ok'       => true,
            'integration' => $this->g->hasIntegrationScopes($workspace),
            'files'    => array_map(fn ($f) => [
                'id'             => (string) ($f['id'] ?? ''),
                'name'           => (string) ($f['name'] ?? ''),
                'modified_time'  => (string) ($f['modifiedTime'] ?? ''),
                'web_view_link'  => (string) ($f['webViewLink'] ?? ''),
            ], $files),
        ]);
    }

    /** GET /google-account/forms/{formId}/apps-script.gs */
    public function appsScript(Request $request, string $formId): Response
    {
        $user = Auth::user();
        $workspace = $user ? Workspace::find($user->current_workspace_id) : null;
        if (!$workspace) {
            return response('Not logged in.', 401);
        }

        // Mint a fresh "anchor" row tied to this workspace + form. The
        // row sits unused until a flow actually fires the node; on that
        // event we mint a per-session row with a fresh webhook_token.
        // The script gets the workspace's Node token (same shared
        // secret Node uses), so any unsubmitted session matches.
        $token = node_token();

        $src = $this->g->buildAppsScript(
            url('/api/google/form-response'),
            $token,
            $formId,
        );

        // Brand-slug download name so a re-branded install never serves a
        // hardcoded "wadesk-form-*.gs".
        $slug = \Illuminate\Support\Str::slug((string) brand_name()) ?: 'form';
        return response($src, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $slug . '-form-' . preg_replace('/[^A-Za-z0-9_-]/', '', $formId) . '.gs"',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────

    private function authedNode(Request $request): bool
    {
        $expected = node_token();
        $token    = (string) $request->header(self::TOKEN_HEADER, '');
        return $expected !== '' && hash_equals($expected, $token);
    }

    /**
     * 503 short-circuit when the platform admin has flipped the Google
     * master toggle OFF at /admin/settings/google-calendar. Without
     * this gate, every previously-connected workspace would keep
     * running Sheets/Docs/Forms nodes until tokens expire — which is
     * not what an admin who just turned the integration off expects.
     * Returns null when the integration is enabled (caller proceeds);
     * returns a 503 JsonResponse to short-circuit on.
     */
    private function integrationDisabledOrNull(): ?JsonResponse
    {
        $gcal = app(\App\Services\GoogleCalendar\GoogleCalendarService::class);
        if ($gcal->isEnabled()) return null;
        return response()->json([
            'ok'      => false,
            'error'   => 'integration_disabled',
            'message' => 'Google integration is disabled platform-wide. Ask your admin to re-enable it at /admin/settings/google-calendar.',
        ], 503);
    }
}

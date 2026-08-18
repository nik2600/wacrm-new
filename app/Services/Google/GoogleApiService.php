<?php

namespace App\Services\Google;

use App\Models\Workspace;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sheets / Docs / Drive / Forms helpers — reuses the workspace's existing
 * Google OAuth token (stored in workspaces.appointment_settings.google_oauth)
 * via GoogleCalendarService::ensureFreshToken().
 *
 * Endpoints verified against developers.google.com on 2026-05-24:
 *   - Sheets:   sheets.googleapis.com/v4/spreadsheets/{id}/values/{range}:append
 *               sheets.googleapis.com/v4/spreadsheets/{id}/values/{range}
 *   - Docs:     docs.googleapis.com/v1/documents/{id}
 *               docs.googleapis.com/v1/documents/{id}:batchUpdate
 *   - Drive:    www.googleapis.com/drive/v3/files (copy via /{id}/copy)
 *               www.googleapis.com/drive/v3/files/{id}/permissions
 *   - Forms:    forms.googleapis.com/v1/forms/{id}
 *               forms.googleapis.com/v1/forms/{id}/responses
 */
class GoogleApiService
{
    private const TIMEOUT = 20;

    public function __construct(private readonly GoogleCalendarService $oauth) {}

    /** True iff the workspace has connected Google AND the new scopes are present. */
    public function hasIntegrationScopes(Workspace $workspace): array
    {
        $oauth = $workspace->appointment_settings['google_oauth'] ?? [];
        $token = (string) ($oauth['access_token'] ?? '');
        $scopes = (array) ($oauth['scopes'] ?? []);
        if ($token === '') {
            return ['connected' => false, 'missing' => ['(not connected)']];
        }
        $required = [
            // Calendar is needed for BookAppointment slot lookup + booking
            // + Google Meet link creation. A workspace whose admin manually
            // edited google_calendar_scopes to drop calendar would still
            // report ok=true here while BookAppointment/Meet silently 403'd
            // at runtime. Added 2026-06-22.
            'calendar' => 'https://www.googleapis.com/auth/calendar',
            'sheets'   => 'https://www.googleapis.com/auth/spreadsheets',
            'docs'     => 'https://www.googleapis.com/auth/documents',
            // Full drive (not drive.file) — required so the pickers can LIST
            // the user's existing files and the Docs node can copy a user
            // template. An older connection holding only drive.file is treated
            // as missing here so the re-consent banner prompts a reconnect.
            'drive'    => 'https://www.googleapis.com/auth/drive',
            'forms'    => 'https://www.googleapis.com/auth/forms.body.readonly',
        ];
        $missing = [];
        foreach ($required as $key => $url) {
            if (!in_array($url, $scopes, true)) $missing[] = $key;
        }
        return ['connected' => true, 'missing' => $missing, 'ok' => empty($missing)];
    }

    // ───────────────────────── SHEETS ─────────────────────────

    /**
     * Append a row to a sheet. Sheet ID = the file's Google Drive id,
     * range = "Sheet1!A:Z" style (default first tab). Values is an
     * array of strings — order is the column order in the sheet.
     */
    public function appendSheetRow(Workspace $workspace, string $sheetId, string $tabName, array $values): array
    {
        $token = $this->oauth->ensureFreshToken($workspace);
        Log::info('[FLOWTRACE] gsheet append IN', [
            'workspace_id' => (int) $workspace->id,
            'sheet_id'     => $sheetId,
            'tab'          => $tabName,
            'token'        => $token ? 'present' : 'MISSING — Google not connected / token refresh failed',
            'col_count'    => count($values),
            'values'       => array_map(fn ($v) => mb_substr((string) $v, 0, 40), $values),
        ]);
        if (!$token) return ['ok' => false, 'error' => 'Google not connected'];

        $range = $tabName !== '' ? rawurlencode($tabName . '!A:Z') : 'A:Z';
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values/{$range}:append"
             . '?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';
        try {
            $r = Http::withToken($token)->timeout(self::TIMEOUT)->post($url, [
                'values' => [$values],
            ]);
            if ($r->successful()) {
                $updated = (string) $r->json('updates.updatedRange', '');
                Log::info('[FLOWTRACE] gsheet append OK', ['updated_range' => $updated]);
                return ['ok' => true, 'updated_range' => $updated];
            }
            $err = (string) ($r->json('error.message') ?: $r->status());
            Log::warning('[FLOWTRACE] gsheet append FAILED status=' . $r->status() . ' err=' . $err
                . ' — likely the connected Google account cannot access this sheet (share it as Editor) or the sheet id is wrong.');
            return ['ok' => false, 'error' => $err];
        } catch (\Throwable $e) {
            Log::warning('[FLOWTRACE] gsheet append EXCEPTION: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Read rows from a sheet — used by the lookup mode of the Sheets node.
     * Returns array of associative rows keyed by the first row (header).
     * matchColumn + matchValue does the "find row where col=value" filter.
     */
    public function readSheetRows(Workspace $workspace, string $sheetId, string $tabName, ?string $matchColumn = null, ?string $matchValue = null, int $limit = 50): array
    {
        $token = $this->oauth->ensureFreshToken($workspace);
        if (!$token) return ['ok' => false, 'error' => 'Google not connected'];

        $range = $tabName !== '' ? rawurlencode($tabName . '!A:Z') : 'A:Z';
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values/{$range}";
        try {
            $r = Http::withToken($token)->timeout(self::TIMEOUT)->get($url);
            if (!$r->successful()) {
                return ['ok' => false, 'error' => (string) ($r->json('error.message') ?: $r->status())];
            }
            $raw = (array) $r->json('values', []);
            if (count($raw) < 2) return ['ok' => true, 'rows' => []];

            $headers = array_map(fn ($h) => (string) $h, $raw[0]);
            $rows = [];
            for ($i = 1; $i < count($raw) && count($rows) < $limit; $i++) {
                $row = [];
                foreach ($headers as $j => $h) {
                    $row[$h] = (string) ($raw[$i][$j] ?? '');
                }
                if ($matchColumn !== null && $matchValue !== null) {
                    if (($row[$matchColumn] ?? null) !== $matchValue) continue;
                }
                $rows[] = $row;
            }
            return ['ok' => true, 'rows' => $rows];
        } catch (\Throwable $e) {
            Log::warning('[GSheets] read exception: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Read just the header row (row 1) of a sheet tab. Powers the Sheets WRITE
     * node's opt-in "match columns by header" mode — the caller pairs each
     * column value with the sheet column whose header matches. Returns
     * ['ok'=>bool, 'headers'=>string[]] (empty headers when the sheet has no
     * header row yet, so the caller can fall back to a positional append).
     */
    public function readSheetHeaders(Workspace $workspace, string $sheetId, string $tabName): array
    {
        $token = $this->oauth->ensureFreshToken($workspace);
        if (!$token) return ['ok' => false, 'error' => 'Google not connected', 'headers' => []];

        $range = $tabName !== '' ? rawurlencode($tabName . '!1:1') : '1:1';
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values/{$range}";
        try {
            $r = Http::withToken($token)->timeout(self::TIMEOUT)->get($url);
            if (!$r->successful()) {
                return ['ok' => false, 'error' => (string) ($r->json('error.message') ?: $r->status()), 'headers' => []];
            }
            $raw = (array) $r->json('values', []);
            $headers = (isset($raw[0]) && is_array($raw[0])) ? array_map(fn ($h) => (string) $h, $raw[0]) : [];
            return ['ok' => true, 'headers' => $headers];
        } catch (\Throwable $e) {
            Log::warning('[GSheets] header read exception: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage(), 'headers' => []];
        }
    }

    // ────────────────────────── DOCS ──────────────────────────

    /**
     * Copy a template doc → fill {{placeholders}} with $vars → optionally
     * make it shareable (anyone-with-link) → return the new doc URL.
     * Two API calls: drive.copy, then docs.batchUpdate with replaceAllText.
     */
    public function copyDocAndFill(Workspace $workspace, string $templateDocId, string $newTitle, array $vars, bool $shareable = true): array
    {
        $token = $this->oauth->ensureFreshToken($workspace);
        if (!$token) return ['ok' => false, 'error' => 'Google not connected'];

        try {
            $copy = Http::withToken($token)->timeout(self::TIMEOUT)
                ->post("https://www.googleapis.com/drive/v3/files/{$templateDocId}/copy", [
                    'name' => $newTitle,
                ]);
            if (!$copy->successful()) {
                return ['ok' => false, 'error' => (string) ($copy->json('error.message') ?: 'doc copy failed')];
            }
            $newId = (string) $copy->json('id');

            // Build replaceAllText requests for every var. Match BOTH {{var}}
            // and {var} so docs authored either way work.
            $requests = [];
            foreach ($vars as $k => $v) {
                $val = (string) $v;
                foreach (['{{' . $k . '}}', '{' . $k . '}'] as $needle) {
                    $requests[] = ['replaceAllText' => [
                        'containsText' => ['text' => $needle, 'matchCase' => false],
                        'replaceText'  => $val,
                    ]];
                }
            }
            if (!empty($requests)) {
                $upd = Http::withToken($token)->timeout(self::TIMEOUT)
                    ->post("https://docs.googleapis.com/v1/documents/{$newId}:batchUpdate", [
                        'requests' => $requests,
                    ]);
                if (!$upd->successful()) {
                    Log::warning('[GDocs] batchUpdate failed: ' . substr((string) $upd->body(), 0, 200));
                    // Don't abort — the doc was copied, fill failed but link is usable.
                }
            }

            if ($shareable) {
                Http::withToken($token)->timeout(self::TIMEOUT)
                    ->post("https://www.googleapis.com/drive/v3/files/{$newId}/permissions", [
                        'role' => 'reader',
                        'type' => 'anyone',
                    ]);
            }

            return [
                'ok'  => true,
                'id'  => $newId,
                'url' => "https://docs.google.com/document/d/{$newId}/edit",
            ];
        } catch (\Throwable $e) {
            Log::warning('[GDocs] copyAndFill exception: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ───────────────────────── DRIVE (pickers) ─────────────────

    /**
     * List Drive files of a given mime type — drives the picker UIs in the
     * flow builder for the Sheets/Docs/Forms node configs. Limited to
     * files the user owns or has been shared with them.
     */
    public function listDriveFiles(Workspace $workspace, string $mimeType, int $pageSize = 100): array
    {
        $token = $this->oauth->ensureFreshToken($workspace);
        if (!$token) return [];

        try {
            $r = Http::withToken($token)->timeout(self::TIMEOUT)
                ->get('https://www.googleapis.com/drive/v3/files', [
                    'q'        => "mimeType='{$mimeType}' and trashed=false",
                    'pageSize' => $pageSize,
                    'orderBy'  => 'modifiedTime desc',
                    'fields'   => 'files(id,name,modifiedTime,webViewLink)',
                ]);
            if ($r->successful()) {
                return (array) $r->json('files', []);
            }
        } catch (\Throwable $e) {
            Log::warning('[GDrive] list exception: ' . $e->getMessage());
        }
        return [];
    }

    public function listSheets(Workspace $workspace): array
    {
        return $this->listDriveFiles($workspace, 'application/vnd.google-apps.spreadsheet');
    }

    public function listDocs(Workspace $workspace): array
    {
        return $this->listDriveFiles($workspace, 'application/vnd.google-apps.document');
    }

    public function listForms(Workspace $workspace): array
    {
        return $this->listDriveFiles($workspace, 'application/vnd.google-apps.form');
    }

    // ───────────────────────── FORMS ──────────────────────────

    /**
     * Pull the public form URL + structure. We need the responderUri to
     * send the link to customers, and the items list to know how to map
     * Apps Script webhook payloads onto flow variables.
     */
    public function getForm(Workspace $workspace, string $formId): array
    {
        $token = $this->oauth->ensureFreshToken($workspace);
        if (!$token) return ['ok' => false, 'error' => 'Google not connected'];

        try {
            $r = Http::withToken($token)->timeout(self::TIMEOUT)
                ->get("https://forms.googleapis.com/v1/forms/{$formId}");
            if ($r->successful()) {
                return ['ok' => true, 'form' => (array) $r->json()];
            }
            return ['ok' => false, 'error' => (string) ($r->json('error.message') ?: $r->status())];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate the Apps Script .gs source the user pastes into their
     * form's Script Editor. Bakes in the webhook URL + a workspace-
     * specific token so the script doesn't need any further config.
     * Returns the source as a string.
     */
    public function buildAppsScript(string $webhookUrl, string $workspaceToken, string $formId): string
    {
        // Apps Script source — onFormSubmit trigger reads the latest
        // response and POSTs it to our webhook. UrlFetchApp follows
        // redirects + raises on >=400.
        $tokenJs = Str::of($workspaceToken)->replaceMatches('/[^A-Za-z0-9_\-]/', '');
        $formJs  = Str::of($formId)->replaceMatches('/[^A-Za-z0-9_\-]/', '');
        $urlJs   = addslashes($webhookUrl);
        // Human-readable brand (comment header + log). The JS IDENTIFIERS below
        // stay brand-neutral/generic — a dynamic brand can contain spaces or
        // punctuation that would make an invalid JS name, so only the text
        // strings are branded, never the const/function names.
        $brand   = addslashes((string) brand_name());

        return <<<GS
/**
 * {$brand} → Google Forms bridge.
 * Auto-generated for form {$formJs}.
 *
 * Setup (once per form):
 *   1. Open your Form → ⋮ menu → Script editor
 *   2. Paste this entire file, save
 *   3. Click the clock icon (Triggers) → Add Trigger:
 *        function: onFormSubmit
 *        event:    From form → On form submit
 *        save → Google will ask for permissions, grant them
 *   4. Done — submissions now resume the paused WhatsApp flow.
 */

const FORM_WEBHOOK_URL = "{$urlJs}";
const FORM_WORKSPACE_TOKEN = "{$tokenJs}";
const FORM_ID = "{$formJs}";

function onFormSubmit(e) {
  try {
    const itemResponses = e.response.getItemResponses();
    const answers = {};
    itemResponses.forEach(function (ir) {
      const title = ir.getItem().getTitle();
      const value = ir.getResponse();
      answers[title] = value;
    });

    const payload = {
      form_id:        FORM_ID,
      response_id:    e.response.getId(),
      respondent:     e.response.getRespondentEmail() || "",
      submitted_at:   e.response.getTimestamp().toISOString(),
      answers:        answers,
    };

    UrlFetchApp.fetch(FORM_WEBHOOK_URL, {
      method:             "post",
      contentType:        "application/json",
      headers: {
        "X-Workspace-Token": FORM_WORKSPACE_TOKEN,
      },
      payload:            JSON.stringify(payload),
      muteHttpExceptions: true,
    });
  } catch (err) {
    console.error("[{$brand}] form submit hook failed:", err);
  }
}
GS;
    }
}

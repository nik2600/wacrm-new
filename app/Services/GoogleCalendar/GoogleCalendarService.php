<?php

namespace App\Services\GoogleCalendar;

use App\Models\SystemSetting;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Calendar OAuth + REST helpers. Endpoints verified against
 * developers.google.com on 2026-05-15:
 *   - Auth:    https://accounts.google.com/o/oauth2/v2/auth
 *   - Token:   https://oauth2.googleapis.com/token   (form POST)
 *   - Revoke:  https://oauth2.googleapis.com/revoke  (form POST)
 *   - FreeBusy:        POST /calendar/v3/freeBusy
 *   - Events insert:   POST /calendar/v3/calendars/{calendarId}/events
 *   - Events delete:   DELETE /calendar/v3/calendars/{calendarId}/events/{eventId}
 *   - CalendarList:    GET  /calendar/v3/users/me/calendarList
 *
 * Tokens live inside `workspaces.appointment_settings.google_oauth`
 * (the whole JSON column is `encrypted:array` cast on the model, so the
 * tokens land encrypted at rest without per-field plumbing).
 *
 * Google returns a `refresh_token` only when the auth request set
 * `access_type=offline` AND `prompt=consent` — without prompt=consent a
 * re-auth yields an empty refresh_token, silently breaking renewal.
 */
class GoogleCalendarService
{
    private const HTTP_TIMEOUT_SECONDS = 15;

    public const AUTH_URL    = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    public const REVOKE_URL  = 'https://oauth2.googleapis.com/revoke';
    public const API_BASE    = 'https://www.googleapis.com/calendar/v3';

    /**
     * Default scope list — covers every Google integration the platform
     * uses today so a single OAuth consent unlocks all flow nodes:
     *
     *   openid/email/profile  → the /userinfo call after consent, so the
     *                            connected-account email + name + avatar show
     *                            on /google-account. The calendar (or any
     *                            resource) scope does NOT grant userinfo —
     *                            these three must be requested explicitly or
     *                            userinfo returns 401 and the identity is blank.
     *   calendar              → BookAppointment + Google Meet (events.insert
     *                            with conferenceData=hangoutsMeet)
     *   spreadsheets          → Google Sheets node (read + append)
     *   documents             → Google Docs node (read + batchUpdate)
     *   drive                 → REQUIRED (not drive.file) so the flow-builder
     *                            pickers can LIST the user's existing
     *                            Sheets/Docs/Forms (drive.file only ever sees
     *                            files THIS app created, so a picker under it
     *                            is always empty), and so the Docs node can
     *                            files.copy a user-owned template + share it.
     *                            Verified against developers.google.com 2026-06.
     *   forms.body.readonly   → Google Forms picker (lists workspace forms)
     *
     * Previously only `calendar` was requested by default — Sheets/Docs/
     * Forms nodes then silently failed at runtime with "missing scope"
     * until the operator manually re-consented. Bundling them here means
     * the first consent screen offers the full set and every flow node
     * works without a second trip through the OAuth flow.
     *
     * Operators on a stricter security posture can override via the
     * `google_calendar_scopes` SystemSetting to drop any scopes they
     * don't want — the admin UI exposes this.
     */
    public const DEFAULT_SCOPES = 'openid'
        . ' https://www.googleapis.com/auth/userinfo.email'
        . ' https://www.googleapis.com/auth/userinfo.profile'
        . ' https://www.googleapis.com/auth/calendar'
        . ' https://www.googleapis.com/auth/spreadsheets'
        . ' https://www.googleapis.com/auth/documents'
        . ' https://www.googleapis.com/auth/drive'
        . ' https://www.googleapis.com/auth/forms.body.readonly';

    public function clientId(): string     { return (string) SystemSetting::get('google_calendar_client_id', ''); }
    public function clientSecret(): string { return (string) SystemSetting::get('google_calendar_client_secret', ''); }
    public function scopes(): string
    {
        $s = trim((string) SystemSetting::get('google_calendar_scopes', self::DEFAULT_SCOPES));
        // Guard against a blank or mis-typed row (e.g. one stored with type=int,
        // which casts the scope URLs to "0"). Requesting scope="" / "0" makes
        // every OAuth connect fail, so fall back to the full default set.
        return ($s === '' || !str_contains($s, 'googleapis.com')) ? self::DEFAULT_SCOPES : $s;
    }
    public function redirectUri(): string  { return (string) (SystemSetting::get('google_calendar_redirect_uri') ?: url('/appointments/oauth/google/callback')); }
    public function isEnabled(): bool      { return (bool) SystemSetting::get('google_calendar_enabled', false); }

    public function authorizeUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => $this->scopes(),
            // offline + prompt=consent => refresh_token returned on every consent.
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        try {
            $r = Http::asForm()->timeout(self::HTTP_TIMEOUT_SECONDS)->post(self::TOKEN_URL, [
                'code'          => $code,
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri'  => $this->redirectUri(),
                'grant_type'    => 'authorization_code',
            ]);
            if ($r->successful()) {
                return [
                    'success'       => true,
                    'access_token'  => (string) $r->json('access_token', ''),
                    'refresh_token' => (string) $r->json('refresh_token', ''),
                    'expires_in'    => (int) $r->json('expires_in', 3600),
                    'scope'         => (string) $r->json('scope', $this->scopes()),
                ];
            }
            return ['success' => false, 'error' => $r->json('error_description') ?: ($r->json('error') ?: 'HTTP ' . $r->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Refresh an access token using the stored refresh_token. Google
     * does NOT rotate refresh_tokens on every refresh — only the access
     * token + expires_in come back. So we only overwrite the
     * access_token + expires_at fields.
     */
    public function refreshAccessToken(Workspace $workspace): bool
    {
        $settings = $workspace->appointment_settings ?? [];
        $rt = $settings['google_oauth']['refresh_token'] ?? '';
        if ($rt === '') return false;

        try {
            $r = Http::asForm()->timeout(self::HTTP_TIMEOUT_SECONDS)->post(self::TOKEN_URL, [
                'refresh_token' => $rt,
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'grant_type'    => 'refresh_token',
            ]);
            if (!$r->successful()) {
                Log::warning('[GCAL] refresh failed status=' . $r->status() . ' body=' . substr((string) $r->body(), 0, 200));
                return false;
            }
            $settings['google_oauth']['access_token'] = (string) $r->json('access_token');
            $settings['google_oauth']['expires_at']   = now()->addSeconds((int) $r->json('expires_in', 3600))->toIso8601String();
            $workspace->appointment_settings = $settings;
            $workspace->save();
            return true;
        } catch (\Throwable $e) {
            Log::warning('[GCAL] refresh exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure access_token is fresh; refresh if within 60s of expiry.
     *
     * Concurrency-safe: wraps the refresh in a per-workspace cache lock
     * so two requests racing the expiry window don't both POST to
     * /token (which can cause one to receive 'invalid_grant' and
     * mid-flight token flip-flopping on the workspace row). The losing
     * caller waits up to 5s for the lock, then reads the freshly-
     * persisted token from $workspace->fresh().
     */
    public function ensureFreshToken(Workspace $workspace): ?string
    {
        $settings = $workspace->appointment_settings ?? [];
        $expiresAt = $settings['google_oauth']['expires_at'] ?? null;
        $needsRefresh = !$expiresAt || Carbon::parse($expiresAt)->lt(now()->addSeconds(60));
        if (!$needsRefresh) {
            return $settings['google_oauth']['access_token'] ?? null;
        }

        $lockKey = 'gcal:refresh:' . $workspace->id;
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);
        try {
            // Block up to 5s waiting for the in-flight refresh to land.
            // When we acquire the lock, re-check expiry: another request
            // may have already refreshed while we were waiting (avoids
            // a redundant /token call).
            $lock->block(5);
            $fresh = $workspace->fresh();
            $latestSettings = $fresh?->appointment_settings ?? $settings;
            $latestExpiry   = $latestSettings['google_oauth']['expires_at'] ?? null;
            if ($latestExpiry && Carbon::parse($latestExpiry)->gte(now()->addSeconds(60))) {
                return $latestSettings['google_oauth']['access_token'] ?? null;
            }
            if (!$this->refreshAccessToken($workspace)) return null;
            $settings = $workspace->fresh()->appointment_settings ?? [];
            return $settings['google_oauth']['access_token'] ?? null;
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            // Couldn't grab the lock within 5s — fall back to reading
            // whatever's on disk now (a parallel refresher probably
            // succeeded). Don't trigger our own refresh under contention.
            $settings = $workspace->fresh()->appointment_settings ?? [];
            return $settings['google_oauth']['access_token'] ?? null;
        } finally {
            optional($lock)->release();
        }
    }

    /** GET /users/me/calendarList — for the settings dropdown. */
    public function listCalendars(Workspace $workspace): array
    {
        $token = $this->ensureFreshToken($workspace);
        if (!$token) return [];
        try {
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . '/users/me/calendarList', ['minAccessRole' => 'writer']);
            if ($r->successful()) return (array) $r->json('items', []);
        } catch (\Throwable $e) {
            Log::warning('[GCAL] listCalendars: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * POST /freeBusy — returns busy intervals for the workspace's
     * connected calendar between timeMin and timeMax. Caller subtracts
     * those from configured availability windows to compute open slots.
     */
    public function freeBusy(Workspace $workspace, string $calendarId, Carbon $timeMin, Carbon $timeMax, string $timeZone = 'UTC'): array
    {
        $token = $this->ensureFreshToken($workspace);
        if (!$token) return [];
        try {
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/freeBusy', [
                    'timeMin'  => $timeMin->toRfc3339String(),
                    'timeMax'  => $timeMax->toRfc3339String(),
                    'timeZone' => $timeZone,
                    'items'    => [['id' => $calendarId]],
                ]);
            if ($r->successful()) {
                return (array) ($r->json('calendars.' . $calendarId . '.busy') ?? []);
            }
            Log::warning('[GCAL] freeBusy status=' . $r->status() . ' body=' . substr((string) $r->body(), 0, 200));
        } catch (\Throwable $e) {
            Log::warning('[GCAL] freeBusy exception: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * POST /calendars/{id}/events — create a calendar event for a
     * confirmed booking. Returns Google event id on success, null on
     * failure. Attendees array is optional; supplying it triggers
     * Google's own email invites if sendUpdates=all.
     */
    public function createEvent(Workspace $workspace, string $calendarId, array $eventPayload, bool $sendUpdates = false): ?array
    {
        $token = $this->ensureFreshToken($workspace);
        if (!$token) return null;
        try {
            $url = self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events'
                 . ($sendUpdates ? '?sendUpdates=all' : '');
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)->post($url, $eventPayload);
            if ($r->successful()) {
                return (array) $r->json();
            }
            Log::warning('[GCAL] createEvent status=' . $r->status() . ' body=' . substr((string) $r->body(), 0, 200));
        } catch (\Throwable $e) {
            Log::warning('[GCAL] createEvent exception: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Create a Calendar event with a Google Meet link attached. Google
     * generates the Meet URL when the request body carries a
     * `conferenceData.createRequest` AND the URL has
     * `conferenceDataVersion=1`. Without that query param Calendar
     * silently strips the conference data and the event has no link.
     *
     * Returns the inserted event (so callers can read `hangoutLink` +
     * `id`), or null on failure. Falls back to scanning
     * `conferenceData.entryPoints[*].uri` (type=video) when
     * `hangoutLink` isn't populated yet — Google sometimes returns the
     * URL there for a brief window before promoting it to `hangoutLink`.
     */
    public function createMeetEvent(
        Workspace $workspace,
        string $calendarId,
        string $summary,
        Carbon $start,
        Carbon $end,
        array $attendees = [],
        ?string $description = null,
        string $timeZone = 'UTC',
        bool $sendInvites = false,
    ): ?array {
        $token = $this->ensureFreshToken($workspace);
        if (!$token) return null;

        $payload = [
            'summary'     => $summary,
            'description' => $description ?: null,
            'start'       => ['dateTime' => $start->toRfc3339String(), 'timeZone' => $timeZone],
            'end'         => ['dateTime' => $end->toRfc3339String(),   'timeZone' => $timeZone],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => 'wadesk-' . bin2hex(random_bytes(8)),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ];
        if (!empty($attendees)) {
            $payload['attendees'] = array_values(array_map(fn ($a) => is_array($a)
                ? array_filter(['email' => $a['email'] ?? null, 'displayName' => $a['displayName'] ?? null])
                : ['email' => (string) $a], $attendees));
        }
        // Strip empty fields so Google doesn't reject the body.
        $payload = array_filter($payload, fn ($v) => $v !== null);

        try {
            $url = self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events'
                 . '?conferenceDataVersion=1'
                 . ($sendInvites ? '&sendUpdates=all' : '');
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)->post($url, $payload);
            if ($r->successful()) {
                $event = (array) $r->json();
                if (empty($event['hangoutLink'])) {
                    // Fallback — pull from entryPoints[] when Google
                    // hasn't promoted the URL to hangoutLink yet.
                    foreach (($event['conferenceData']['entryPoints'] ?? []) as $ep) {
                        if (($ep['entryPointType'] ?? null) === 'video' && !empty($ep['uri'])) {
                            $event['hangoutLink'] = $ep['uri'];
                            break;
                        }
                    }
                }
                return $event;
            }
            Log::warning('[GCAL] createMeetEvent status=' . $r->status() . ' body=' . substr((string) $r->body(), 0, 300));
        } catch (\Throwable $e) {
            Log::warning('[GCAL] createMeetEvent exception: ' . $e->getMessage());
        }
        return null;
    }

    public function deleteEvent(Workspace $workspace, string $calendarId, string $eventId): bool
    {
        $token = $this->ensureFreshToken($workspace);
        if (!$token) return false;
        try {
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->delete(self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($eventId));
            return $r->successful() || $r->status() === 410; // 410 = already deleted
        } catch (\Throwable $e) {
            Log::warning('[GCAL] deleteEvent exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * PATCH a Google Calendar event in place — used by the appointment
     * reschedule endpoint so attendees get a "this event moved" email
     * from Google instead of a fresh invite. Only the fields present
     * in $patch are sent (PATCH semantics, not PUT).
     */
    public function updateEvent(Workspace $workspace, string $calendarId, string $eventId, array $patch, bool $sendUpdates = false): ?array
    {
        $token = $this->ensureFreshToken($workspace);
        if (!$token) return null;
        try {
            $url = self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($eventId)
                 . ($sendUpdates ? '?sendUpdates=all' : '');
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->patch($url, $patch);
            if ($r->successful()) return (array) $r->json();
            Log::warning('[GCAL] updateEvent status=' . $r->status() . ' body=' . substr((string) $r->body(), 0, 200));
        } catch (\Throwable $e) {
            Log::warning('[GCAL] updateEvent exception: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Compute the next N free slots for a workspace given its
     * configured availability windows. Strategy:
     *   1. Build candidate slots from availability_windows × today..today+advance_days
     *   2. Pull busy intervals from Google /freeBusy for the same range
     *   3. Drop slots that overlap any busy interval
     *   4. Drop slots already in the past
     *   5. Apply max_per_day cap
     *   6. Take the first $limit results
     *
     * Returns array of [start ISO8601, end ISO8601, label] tuples.
     */
    /**
     * Which calendar this workspace books into.
     *
     * Falls back to Google's `primary` when the account is connected but no
     * specific calendar was ever picked — connecting Google and never opening
     * the calendar dropdown is the normal path, so treating that as "no
     * calendar" is wrong.
     *
     * This MUST be the single source of truth. It used to be resolved in two
     * places with two different rules: the booking path fell back to
     * `primary`, the slot path did not. A merchant who connected Google but
     * skipped the calendar picker therefore got events written INTO their
     * calendar while the slot list read busy times from nothing — so existing
     * meetings never blocked a slot, and the same time could be booked over
     * and over. That is the "slot picked but calendar not marked" report.
     */
    public function resolveCalendarId(Workspace $workspace): ?string
    {
        $g = $workspace->appointment_settings['google_oauth'] ?? [];

        $explicit = trim((string) ($g['calendar_id'] ?? ''));
        if ($explicit !== '') return $explicit;

        $connected = !empty($g['refresh_token']) || !empty($g['access_token']);
        return $connected ? 'primary' : null;
    }

    public function computeFreeSlots(Workspace $workspace, int $limit = 5): array
    {
        $settings = $workspace->appointment_settings ?? [];
        // No Google Calendar linked → still offer slots from the availability
        // windows alone (we just can't subtract real busy times). Previously this
        // returned [] so the booking node said "no slots" forever until a calendar
        // was connected — which most WhatsApp-only merchants never do.
        $calendarId = $this->resolveCalendarId($workspace);

        $tz       = $settings['google_oauth']['calendar_timezone'] ?? ($workspace->timezone ?: 'UTC');
        $duration = (int) ($settings['slot_duration_minutes'] ?? 30);
        $bufBefore = (int) ($settings['buffer_before_minutes'] ?? 0);
        $bufAfter  = (int) ($settings['buffer_after_minutes'] ?? 0);
        $maxPerDay = (int) ($settings['max_per_day'] ?? 16);
        $advanceDays = max(1, (int) ($settings['advance_days'] ?? 14));
        $windows = (array) ($settings['availability_windows'] ?? []);

        $start = now($tz)->copy();
        $end   = now($tz)->copy()->addDays($advanceDays);
        $busy  = $calendarId ? $this->freeBusy($workspace, $calendarId, $start, $end, $tz) : [];

        // Also block slots this workspace has ALREADY booked in WaDesk.
        //
        // Google's free/busy was the only source here, which meant a booked
        // slot stayed bookable whenever the Google event didn't exist: no
        // calendar connected at all, or createEvent failed (missing `calendar`
        // scope / expired refresh token — both of which only log a warning and
        // still return "Booked!" to the customer). Two customers could then
        // take the same time. Our own appointments table is authoritative for
        // what we sold, so it belongs in the busy set regardless of Google.
        try {
            \App\Models\Appointment::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('ends_at', '>', $start)
                ->where('starts_at', '<', $end)
                ->get(['starts_at', 'ends_at'])
                ->each(function ($a) use (&$busy) {
                    $busy[] = [
                        'start' => Carbon::parse($a->starts_at)->toIso8601String(),
                        'end'   => Carbon::parse($a->ends_at)->toIso8601String(),
                    ];
                });
        } catch (\Throwable $e) {
            // Never fail slot generation over this — worst case we fall back
            // to Google-only busy times, which is the old behaviour.
            Log::warning('[GCAL] local appointment busy-merge failed: ' . $e->getMessage());
        }

        $slots = [];
        $cursor = $start->copy();
        while ($cursor->lt($end) && count($slots) < $limit * 4) {
            $dayKey = strtolower($cursor->format('D')); // mon, tue, ...
            $dayWindows = $windows[$dayKey] ?? [];
            if (empty($dayWindows)) { $cursor->addDay()->startOfDay(); continue; }

            $daySlots = 0;
            foreach ($dayWindows as $w) {
                if (empty($w['from']) || empty($w['to'])) continue;
                $winStart = $cursor->copy()->setTimeFromTimeString($w['from']);
                $winEnd   = $cursor->copy()->setTimeFromTimeString($w['to']);
                $slotStart = $winStart->copy();
                while ($slotStart->copy()->addMinutes($duration)->lte($winEnd) && $daySlots < $maxPerDay && count($slots) < $limit * 4) {
                    $slotEnd = $slotStart->copy()->addMinutes($duration);
                    if ($slotStart->gte(now($tz))) {
                        $clashStart = $slotStart->copy()->subMinutes($bufBefore);
                        $clashEnd   = $slotEnd->copy()->addMinutes($bufAfter);
                        $overlaps = false;
                        foreach ($busy as $b) {
                            $bs = Carbon::parse($b['start']);
                            $be = Carbon::parse($b['end']);
                            if ($bs->lt($clashEnd) && $be->gt($clashStart)) { $overlaps = true; break; }
                        }
                        if (!$overlaps) {
                            $slots[] = [
                                'start' => $slotStart->copy()->toIso8601String(),
                                'end'   => $slotEnd->copy()->toIso8601String(),
                                'label' => $slotStart->format('D j M · g:i A'),
                            ];
                            $daySlots++;
                        }
                    }
                    $slotStart->addMinutes($duration);
                }
            }
            $cursor->addDay()->startOfDay();
        }

        return array_slice($slots, 0, $limit);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $gcal) {}

    /** GET /appointments — calendar + list view of upcoming bookings. */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $wsId = (int) $user?->current_workspace_id;

        $upcoming = $wsId
            ? Appointment::forWorkspace($wsId)->forCurrentEngine()->upcoming()->orderBy('starts_at')->limit(50)->get()
            : collect();
        $past = $wsId
            ? Appointment::forWorkspace($wsId)
                ->forCurrentEngine()
                ->where('starts_at', '<', now())
                ->orderByDesc('starts_at')->limit(20)->get()
            : collect();

        $workspace = $wsId ? Workspace::find($wsId) : null;
        // "Connected" == we can resolve a calendar to write into. Requiring an
        // explicitly-picked calendar_id here showed "not connected" to a
        // merchant who had authorised Google but never opened the calendar
        // dropdown — while bookings were in fact being written to their
        // primary calendar. Same resolver as the slot + booking paths.
        $isConnected = $workspace ? ($this->gcal->resolveCalendarId($workspace) !== null) : false;

        return view('user.appointments.index', [
            'upcoming'    => $upcoming,
            'past'        => $past,
            'isConnected' => $isConnected,
            'settings'    => $workspace?->appointment_settings ?? [],
        ]);
    }

    /** GET /appointments/settings — Google OAuth + availability windows form. */
    public function settings(Request $request): View
    {
        $user = Auth::user();
        $wsId = (int) $user?->current_workspace_id;
        $workspace = $wsId ? Workspace::find($wsId) : null;

        $settings = $workspace?->appointment_settings ?? [];
        $oauth    = $settings['google_oauth'] ?? [];
        $isConnected = !empty($oauth['access_token'] ?? null);

        $calendars = $isConnected ? $this->gcal->listCalendars($workspace) : [];

        return view('user.appointments.settings', [
            'workspace'   => $workspace,
            'settings'    => $settings,
            'isConnected' => $isConnected,
            'oauth'       => $oauth,
            'calendars'   => $calendars,
            'appEnabled'  => $this->gcal->isEnabled() && $this->gcal->clientId() !== '',
            'defaultWindows' => self::defaultAvailabilityWindows(),
        ]);
    }

    /** POST /appointments/settings — persist availability + booking config. */
    public function saveSettings(Request $request)
    {
        $user = Auth::user();
        $wsId = (int) $user?->current_workspace_id;
        $workspace = $wsId ? Workspace::find($wsId) : null;
        if (!$workspace) return back()->with('error', 'No workspace.');

        $validated = $request->validate([
            'calendar_id'             => ['nullable', 'string', 'max:191'],
            'calendar_name'           => ['nullable', 'string', 'max:191'],
            'calendar_timezone'       => ['nullable', 'string', 'max:64'],
            'slot_duration_minutes'   => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_before_minutes'   => ['required', 'integer', 'min:0', 'max:240'],
            'buffer_after_minutes'    => ['required', 'integer', 'min:0', 'max:240'],
            'max_per_day'             => ['required', 'integer', 'min:1', 'max:96'],
            'advance_days'            => ['required', 'integer', 'min:1', 'max:90'],
            'reminder_minutes_before' => ['required', 'integer', 'min:0', 'max:1440'],
            'default_location'        => ['nullable', 'string', 'max:191'],
            'availability_windows'    => ['nullable', 'array'],
        ]);

        $settings = $workspace->appointment_settings ?? [];
        if (!empty($validated['calendar_id'])) {
            $settings['google_oauth']['calendar_id']       = $validated['calendar_id'];
            $settings['google_oauth']['calendar_name']     = $validated['calendar_name'] ?? '';
            $settings['google_oauth']['calendar_timezone'] = $validated['calendar_timezone'] ?? ($workspace->timezone ?: 'UTC');
        }
        $settings['slot_duration_minutes']   = (int) $validated['slot_duration_minutes'];
        $settings['buffer_before_minutes']   = (int) $validated['buffer_before_minutes'];
        $settings['buffer_after_minutes']    = (int) $validated['buffer_after_minutes'];
        $settings['max_per_day']             = (int) $validated['max_per_day'];
        $settings['advance_days']            = (int) $validated['advance_days'];
        $settings['reminder_minutes_before'] = (int) $validated['reminder_minutes_before'];
        $settings['default_location']        = (string) ($validated['default_location'] ?? '');
        $settings['availability_windows']    = self::normalizeWindows($validated['availability_windows'] ?? []);
        $workspace->appointment_settings = $settings;
        $workspace->save();

        return back()->with('success', 'Appointment settings saved.');
    }

    /**
     * GET /api/appointments/slots — used by the flow builder runtime to
     * fetch available slots before sending a WhatsApp List Message.
     */
    public function slotsApi(Request $request): JsonResponse
    {
        // Auth: either signed-in user (UI preview) or Node→Laravel
        // X-Node-Token (flow runtime). A caller-supplied workspace_id
        // is only honored when the Node token is present, otherwise
        // we trust the session's current_workspace_id.
        $expected   = node_token();
        $token      = (string) $request->header('X-Node-Token', '');
        $nodeAuthed = $expected !== '' && hash_equals($expected, $token);
        $authedUser = Auth::user();
        if (!$nodeAuthed && !$authedUser) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $wsId = $nodeAuthed
            ? (int) $request->input('workspace_id')
            : (int) ($authedUser?->current_workspace_id ?? 0);
        $workspace = $wsId ? Workspace::find($wsId) : null;
        if (!$workspace) return response()->json(['ok' => false, 'error' => 'no_workspace'], 404);

        // Master-toggle gate — admin flips google_calendar_enabled OFF at
        // /admin/settings/google-calendar to disable Calendar reads platform-wide.
        // BUT only enforce it when THIS workspace actually uses a Google Calendar:
        // a merchant booking against local availability windows (no calendar
        // linked) never touches Google, so the toggle (which DEFAULTS to off) must
        // not block them — otherwise the booking node returns "no slots" forever.
        $hasCalendar = $this->gcal->resolveCalendarId($workspace) !== null;
        if ($hasCalendar && !$this->gcal->isEnabled()) {
            return response()->json([
                'ok'      => false,
                'error'   => 'integration_disabled',
                'message' => 'Google integration is disabled platform-wide. Ask your admin to re-enable it at /admin/settings/google-calendar.',
            ], 503);
        }

        $limit = (int) min(10, max(1, (int) $request->input('limit', 5)));
        $slots = $this->gcal->computeFreeSlots($workspace, $limit);
        return response()->json(['ok' => true, 'slots' => $slots]);
    }

    /**
     * POST /api/appointments/book — books an appointment + writes to
     * Google Calendar. Used by the flow runtime when the customer picks
     * a slot. Idempotent on (conversation_id, starts_at) so a duplicate
     * tap from the customer doesn't double-book.
     */
    public function bookApi(Request $request): JsonResponse
    {
        // X-Node-Token gate — matches every other Node→Laravel callback
        // route (the flow runtime is the only legit caller). Without
        // this, anyone on the public internet could POST a workspace_id
        // and write to that workspace's Google Calendar / Appointment
        // table.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'workspace_id'     => ['required', 'integer'],
            'starts_at'        => ['required', 'date'],
            'ends_at'          => ['required', 'date'],
            'title'            => ['nullable', 'string', 'max:191'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'contact_id'       => ['nullable', 'integer'],
            'conversation_id'  => ['nullable', 'integer'],
            'customer_name'    => ['nullable', 'string', 'max:191'],
            'customer_phone'   => ['nullable', 'string', 'max:32'],
            'customer_email'   => ['nullable', 'email', 'max:191'],
        ]);

        // Google-Calendar gate + plan flag apply ONLY when this workspace actually
        // syncs to a Google Calendar. Local availability-window booking (no calendar
        // linked) must work without Google — same reasoning as slotsApi.
        $ws = Workspace::find($data['workspace_id']);
        if (!$ws) return response()->json(['ok' => false, 'error' => 'no_workspace'], 404);
        $hasCalendar = $this->gcal->resolveCalendarId($ws) !== null;
        if ($hasCalendar && !$this->gcal->isEnabled()) {
            return response()->json([
                'ok'      => false,
                'error'   => 'integration_disabled',
                'message' => 'Google integration is disabled platform-wide. Ask your admin to re-enable it at /admin/settings/google-calendar.',
            ], 503);
        }

        // Plan: monthly cap only (below). The booking-FEATURE gate is NOT enforced
        // at this final step: slotsApi already offered the slots WITHOUT a plan
        // gate, so throwing here paywalled the customer AFTER they picked a time —
        // and because this is a server-to-server Node call with no Accept:json,
        // PlanLimitReachedException rendered as a redirect → 200 HTML, which the
        // runtime read as booked=false and dropped the customer to "no slots".
        // (Was: PlanLimitGuard::feature($ws, 'access_appointment_booking').) Gate
        // appointment booking at the flow-builder / slot-offer level instead.
        if (!\App\Services\PlanLimitGuard::hasFeature($ws, 'access_appointment_booking')) {
            \Log::info('[APPT] booking feature not on plan — allowing mid-flow booking anyway', ['ws' => $ws->id]);
        }
        if ($hasCalendar) {
            \App\Services\PlanLimitGuard::feature($ws, 'integration_google_calendar');
        }
        \App\Services\PlanLimitGuard::check(
            $ws, 'appointments_limit',
            Appointment::forWorkspace($ws->id)->where('starts_at', '>=', now()->startOfMonth())->count(),
        );

        $workspace = Workspace::find($data['workspace_id']);
        if (!$workspace) return response()->json(['ok' => false, 'error' => 'no_workspace'], 404);

        // Match the conversation scoping pattern below — contacts
        // belong to the workspace now, not the workspace owner alone.
        $contact = !empty($data['contact_id'])
            ? Contact::where('workspace_id', $workspace->id)->find($data['contact_id'])
            : null;
        $conversation = !empty($data['conversation_id'])
            ? Conversation::where('workspace_id', $workspace->id)->find($data['conversation_id'])
            : null;

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt   = Carbon::parse($data['ends_at']);

        $existing = Appointment::forWorkspace($workspace->id)
            ->where('starts_at', $startsAt)
            ->when($conversation, fn ($q) => $q->where('conversation_id', $conversation->id))
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();
        if ($existing) {
            return response()->json(['ok' => true, 'appointment_id' => $existing->id, 'duplicate' => true]);
        }

        $settings   = $workspace->appointment_settings ?? [];
        // Same resolver the slot list uses — see GoogleCalendarService. These
        // two used to disagree, which let bookings write to `primary` while
        // slot generation read busy times from nothing.
        $calendarId = $this->gcal->resolveCalendarId($workspace);
        $tz         = $settings['google_oauth']['calendar_timezone'] ?? ($workspace->timezone ?: 'UTC');

        $title = $data['title'] ?: ('Booking — ' . ($data['customer_name'] ?: 'WhatsApp customer'));
        $appt = Appointment::create([
            'workspace_id'       => $workspace->id,
            'user_id'            => $workspace->owner_user_id,
            'contact_id'         => $contact?->id,
            'conversation_id'    => $conversation?->id,
            'title'              => $title,
            'description'        => $data['description'] ?? null,
            'location'           => $settings['default_location'] ?? null,
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
            'timezone'           => $tz,
            'status'             => 'pending',
            'google_calendar_id' => $calendarId,
            'meta'               => [
                'customer_name'  => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
            ],
        ]);

        if ($calendarId) {
            $payload = [
                'summary'     => $title,
                'description' => $data['description'] ?? '',
                'location'    => $settings['default_location'] ?? '',
                'start'       => ['dateTime' => $startsAt->toRfc3339String(), 'timeZone' => $tz],
                'end'         => ['dateTime' => $endsAt->toRfc3339String(),   'timeZone' => $tz],
                'reminders'   => ['useDefault' => true],
            ];
            if (!empty($data['customer_email'])) {
                $payload['attendees'] = [
                    ['email' => $data['customer_email'], 'displayName' => $data['customer_name'] ?: null],
                ];
            }
            $event = $this->gcal->createEvent($workspace, $calendarId, $payload, sendUpdates: !empty($data['customer_email']));
            if ($event) {
                $appt->update([
                    'google_event_id' => (string) ($event['id'] ?? ''),
                    'status'          => 'confirmed',
                ]);
                $calendarSynced = true;
            } else {
                // createEvent already logged the Google status+body. Surface it
                // WITH booking context so the silent no-op ("Booked!" but nothing
                // in Calendar) is diagnosable. Usual cause: the connected Google
                // account is missing the `calendar` scope, or its refresh_token
                // came back empty (re-connect at /appointments/settings fixes it).
                \Illuminate\Support\Facades\Log::warning(
                    '[APPT] calendar event NOT created — appt=' . $appt->id
                    . ' ws=' . $workspace->id . ' calendarId=' . $calendarId
                    . ' — check the Google scope/refresh_token at /appointments/settings'
                );
                $calendarSynced = false;
            }
        } else {
            \Illuminate\Support\Facades\Log::warning(
                '[APPT] no Google Calendar connected for ws=' . $workspace->id
                . ' — appointment #' . $appt->id . ' saved locally only'
            );
            $calendarSynced = false;
        }

        // Schedule a reminder WhatsApp message after the response is
        // sent — no cron, no queue. Defaults to 60 min before the slot;
        // workspace can override with appointment_settings.reminder_minutes_before.
        // Setting the offset to 0 disables reminders.
        $offsetMinutes = (int) ($settings['reminder_minutes_before'] ?? 60);
        $customerPhone = (string) ($data['customer_phone'] ?? '');
        if ($offsetMinutes > 0 && $customerPhone !== '') {
            $apptId = $appt->id;
            app()->terminating(function () use ($apptId, $offsetMinutes, $customerPhone, $workspace) {
                try {
                    @set_time_limit(0);
                    $fresh = Appointment::find($apptId);
                    if (!$fresh || in_array($fresh->status, ['cancelled', 'declined'], true)) return;
                    $remindAt = $fresh->starts_at->copy()->subMinutes($offsetMinutes);
                    if ($remindAt->isPast()) return; // booking made within the reminder window
                    app(\App\Services\Appointments\AppointmentReminderScheduler::class)
                        ->schedule($workspace, $fresh, $customerPhone, $remindAt);
                } catch (\Throwable $e) {
                    \Log::warning('Appointment reminder schedule failed: ' . $e->getMessage());
                }
            });
        }

        return response()->json([
            'ok'              => true,
            'appointment_id'  => $appt->id,
            'status'          => $appt->fresh()->status,
            // Lets the flow runtime / merchant see whether the Google Calendar
            // write actually happened — false = saved locally but NOT on Google
            // (bad scope / no calendar connected). Never silently "booked".
            'calendar_synced' => $calendarSynced ?? false,
        ]);
    }

    /** POST /appointments/{id}/cancel */
    public function cancel(int $id)
    {
        $user = Auth::user();
        $wsId = (int) $user?->current_workspace_id;
        $appt = Appointment::forWorkspace($wsId)->find($id);
        if (!$appt) abort(404);

        if ($appt->google_event_id && $appt->google_calendar_id) {
            $workspace = Workspace::find($wsId);
            if ($workspace) $this->gcal->deleteEvent($workspace, $appt->google_calendar_id, $appt->google_event_id);
        }
        $appt->update(['status' => 'cancelled']);
        return back()->with('success', 'Appointment cancelled.');
    }

    /**
     * POST /appointments/{id}/reschedule
     * Move an existing appointment to a new starts_at/ends_at pair.
     * Updates the linked Google Calendar event in place (preserving the
     * event id, so any attendee invites get a "time changed" email from
     * Google) and re-arms the reminder for the new time.
     */
    public function reschedule(Request $request, int $id)
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date'],
            // Optional re-notify; defaults to true so the customer sees
            // the new time. Pass false for silent admin reschedules.
            'notify'    => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();
        $wsId = (int) $user?->current_workspace_id;
        $appt = Appointment::forWorkspace($wsId)->find($id);
        if (!$appt) abort(404);
        if (in_array($appt->status, ['cancelled', 'declined'], true)) {
            return response()->json(['ok' => false, 'error' => 'cannot_reschedule_terminal_state'], 422);
        }

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt   = Carbon::parse($data['ends_at']);
        if ($endsAt->lte($startsAt)) {
            return response()->json(['ok' => false, 'error' => 'ends_at must be after starts_at'], 422);
        }

        $workspace = Workspace::find($wsId);
        $settings  = $workspace?->appointment_settings ?? [];
        $tz        = $settings['google_oauth']['calendar_timezone'] ?? ($workspace?->timezone ?: 'UTC');

        $previousStart = $appt->starts_at;
        $appt->update([
            'starts_at' => $startsAt,
            'ends_at'   => $endsAt,
            'timezone'  => $tz,
            'status'    => 'confirmed',
        ]);

        // Mirror the move into Google Calendar (same event id, so
        // attendees get a "this event was rescheduled" notification
        // instead of a new invite).
        if ($appt->google_event_id && $appt->google_calendar_id && $workspace) {
            try {
                $this->gcal->updateEvent($workspace, $appt->google_calendar_id, $appt->google_event_id, [
                    'start' => ['dateTime' => $startsAt->toRfc3339String(), 'timeZone' => $tz],
                    'end'   => ['dateTime' => $endsAt->toRfc3339String(),   'timeZone' => $tz],
                ], sendUpdates: $request->boolean('notify', true));
            } catch (\Throwable $e) {
                \Log::warning('Appointment reschedule (gcal updateEvent) failed: ' . $e->getMessage());
            }
        }

        // Re-arm the reminder for the NEW time. We can't cancel the
        // previous one (it's already a Node-side schedule row) but the
        // scheduler dedupes by (appointment_id, remind_at) so a fresh
        // call simply replaces the prior schedule when the time slid.
        $offsetMinutes = (int) ($settings['reminder_minutes_before'] ?? 60);
        $customerPhone = (string) ($appt->meta['customer_phone'] ?? '');
        if ($offsetMinutes > 0 && $customerPhone !== '' && $workspace) {
            $apptId = $appt->id;
            app()->terminating(function () use ($apptId, $offsetMinutes, $customerPhone, $workspace) {
                try {
                    @set_time_limit(0);
                    $fresh = Appointment::find($apptId);
                    if (!$fresh || in_array($fresh->status, ['cancelled', 'declined'], true)) return;
                    $remindAt = $fresh->starts_at->copy()->subMinutes($offsetMinutes);
                    if ($remindAt->isPast()) return;
                    app(\App\Services\Appointments\AppointmentReminderScheduler::class)
                        ->schedule($workspace, $fresh, $customerPhone, $remindAt);
                } catch (\Throwable $e) {
                    \Log::warning('Reschedule reminder re-arm failed: ' . $e->getMessage());
                }
            });
        }

        return response()->json([
            'ok'              => true,
            'appointment_id'  => $appt->id,
            'previous_starts' => $previousStart?->toIso8601String(),
            'starts_at'       => $startsAt->toIso8601String(),
            'ends_at'         => $endsAt->toIso8601String(),
            'status'          => 'confirmed',
        ]);
    }

    /** Default Mon–Fri 09:00–18:00 availability for a fresh workspace. */
    public static function defaultAvailabilityWindows(): array
    {
        $weekdays = [
            'mon' => [['from' => '09:00', 'to' => '18:00']],
            'tue' => [['from' => '09:00', 'to' => '18:00']],
            'wed' => [['from' => '09:00', 'to' => '18:00']],
            'thu' => [['from' => '09:00', 'to' => '18:00']],
            'fri' => [['from' => '09:00', 'to' => '18:00']],
            'sat' => [],
            'sun' => [],
        ];
        return $weekdays;
    }

    private static function normalizeWindows(array $input): array
    {
        $out = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
            $rows = $input[$d] ?? [];
            $clean = [];
            foreach ((array) $rows as $r) {
                if (empty($r['from']) || empty($r['to'])) continue;
                // The form posts times as 12-hour "09:00 AM" / "06:00 PM" (that is
                // what the picker shows). The old code only accepted 24-hour
                // "HH:MM", so EVERY window was silently dropped → settings looked
                // unsaved AND the booking node found no windows → "no slots ever".
                // Accept both and canonicalize to 24-hour "HH:MM".
                $from = self::to24h((string) $r['from']);
                $to   = self::to24h((string) $r['to']);
                if ($from !== null && $to !== null && $from < $to) {
                    $clean[] = ['from' => $from, 'to' => $to];
                }
            }
            $out[$d] = $clean;
        }
        return $out;
    }

    /** Accept "9:00 AM" / "09:00 AM" / "09:00" / "9:00" → canonical 24h "HH:MM" (or null). */
    private static function to24h(string $t): ?string
    {
        $t = trim(preg_replace('/\s+/', ' ', $t));
        if ($t === '') return null;
        // Already 24-hour with no AM/PM marker.
        if (preg_match('/^\d{1,2}:\d{2}$/', $t) && !preg_match('/[ap]\.?m\.?/i', $t)) {
            [$h, $m] = array_map('intval', explode(':', $t));
            return ($h <= 23 && $m <= 59) ? sprintf('%02d:%02d', $h, $m) : null;
        }
        // 12-hour with AM/PM (any spacing/case), or anything Carbon can read.
        foreach (['g:i A', 'h:i A', 'g:iA', 'h:iA'] as $fmt) {
            try { return \Carbon\Carbon::createFromFormat($fmt, strtoupper($t))->format('H:i'); }
            catch (\Throwable $e) { /* try next */ }
        }
        try { return \Carbon\Carbon::parse($t)->format('H:i'); } catch (\Throwable $e) { return null; }
    }
}

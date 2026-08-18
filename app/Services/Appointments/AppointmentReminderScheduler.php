<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Device;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Books a one-off WhatsApp reminder with the Node bridge so the customer
 * gets a "you have an appointment in X minutes" nudge before their slot.
 *
 * Why this lives here and not in NodeSchedulerClient:
 *   NodeSchedulerClient is built around the ScheduledMessage model — it
 *   needs a row to register against. Appointment reminders aren't
 *   campaigns and shouldn't appear in the user's Scheduled list, so we
 *   talk to Node's `/api/schedule-message-bulk/{phone}` endpoint
 *   directly with a synthetic `scheduleId` derived from the appointment
 *   id. The Node bridge dedupes by scheduleId so a reschedule that
 *   re-calls schedule() with the same appointment id REPLACES the
 *   previous fire rather than stacking a second reminder.
 *
 * No queue. No cron. Caller wraps this in app()->terminating() so the
 * HTTP request that booked the appointment isn't held open by the
 * Node round-trip.
 */
class AppointmentReminderScheduler
{
    public function schedule(Workspace $workspace, Appointment $appt, string $customerPhone, Carbon $remindAt): bool
    {
        $base = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($base === '') {
            Log::info('[APPT-REMINDER] no node bridge url configured — skipping reminder for appt ' . $appt->id);
            return false;
        }

        // Multi-engine: resolve the sender PHONE for the workspace's active
        // engine. For Baileys that's a paired device; for WABA/Twilio it's the
        // connected provider config's number. Node routes the reminder by the
        // `provider` field we forward below and uses this phone only to look up
        // the workspace's creds — so a WABA/Twilio-only workspace (which has no
        // paired Baileys device) still sends its reminders on the right engine
        // instead of silently dropping them.
        $engine = \App\Services\WorkspaceEngine::for($workspace->id);
        $devicePhone = '';
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            $device = $this->resolveDevice($workspace, $appt);
            if ($device) {
                $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';
            }
        } else {
            $cfg = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $workspace->id)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('is_primary')
                ->orderByDesc('id')
                ->first();
            $devicePhone = $cfg ? (preg_replace('/\D+/', '', (string) $cfg->phone_number) ?: '') : '';
        }
        if ($devicePhone === '') {
            Log::info('[APPT-REMINDER] no connected ' . $engine . ' sender for workspace ' . $workspace->id . ' — skipping reminder');
            return false;
        }

        $recipient = preg_replace('/\D+/', '', $customerPhone) ?: '';
        if ($recipient === '') return false;

        $settings = $workspace->appointment_settings ?? [];
        $template = (string) ($settings['reminder_template'] ?? 'Reminder: you have "{title}" coming up at {time}. See you then!');
        $tz       = $settings['google_oauth']['calendar_timezone'] ?? ($workspace->timezone ?: 'UTC');
        $body     = strtr($template, [
            '{title}' => (string) $appt->title,
            '{time}'  => $appt->starts_at->copy()->setTimezone($tz)->format('D j M · g:i A'),
            '{date}'  => $appt->starts_at->copy()->setTimezone($tz)->format('D j M Y'),
        ]);

        // Synthetic id so re-runs (reschedule) replace the old reminder
        // instead of stacking a second one. Negative range keeps it
        // clear of real scheduled_messages.id values.
        $scheduleId = -1000000 - $appt->id;

        try {
            $r = Http::withHeaders([
                    'X-Node-Token' => node_token(),
                ])
                ->timeout(10)
                ->acceptJson()
                ->post(rtrim($base, '/') . '/api/schedule-message-bulk/' . rawurlencode($devicePhone), [
                    'scheduleId'         => $scheduleId,
                    'targetPhoneNumbers' => [$recipient],
                    'message'            => $body,
                    'scheduleDateTime'   => $remindAt->toIso8601String(),
                    'timezone'           => $tz,
                    'messageType'        => 'text',
                    'isTemplate'         => false,
                    // Multi-engine: tell Node which engine this reminder must
                    // go out on so it routes by provider (not the workspace-
                    // wide settings heuristic). Empty/baileys = unchanged.
                    'provider'           => $engine,
                    // Diagnostic crumbs — Node ignores these but they
                    // show up in its logs so ops can trace a stray
                    // reminder back to its appointment.
                    'appointmentId'      => $appt->id,
                    'workspaceId'        => $workspace->id,
                ]);
            return $r->successful();
        } catch (\Throwable $e) {
            Log::warning('[APPT-REMINDER] schedule failed for appt ' . $appt->id . ': ' . $e->getMessage());
            return false;
        }
    }

    private function resolveDevice(Workspace $workspace, Appointment $appt): ?Device
    {
        // If the booking ties back to a conversation, use the same
        // device that conversation already lives on (correct WhatsApp
        // thread continuity).
        if ($appt->conversation_id) {
            $conv = \App\Models\Conversation::find($appt->conversation_id);
            if ($conv && $conv->device_id) {
                $d = Device::find($conv->device_id);
                if ($d && $d->active) return $d;
            }
        }
        // Fallback: workspace's first active device. Cross-workspace
        // safe because we constrain to users in this workspace.
        $userIds = \App\Models\User::query()
            ->where('current_workspace_id', $workspace->id)
            ->pluck('id');
        return Device::query()
            ->whereIn('user_id', $userIds)
            ->where('active', true)
            ->orderBy('id')
            ->first();
    }
}

<?php

namespace App\Services\Scheduled;

use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\WaTemplate;
use App\Services\Whatsapp\TemplateDataBuilder;
use Illuminate\Support\Facades\Log;

/**
 * "Send a test to me first" — the toggle on step 5 of /scheduled/new.
 *
 * Fires ONE preview of a freshly-created schedule to the operator's own
 * WhatsApp number, so they see the real thing before the blast goes out.
 * The toggle existed in the UI for a while as a decorative checkbox with
 * no `name` and no handler; this is the implementation behind it.
 *
 * Engine routing mirrors the real send rather than reimplementing it:
 *
 *   WABA + template  → Waba\TemplateSender (a plain text send to a cold
 *                      number is rejected by Meta outside the 24-hour
 *                      customer-service window, so the preview has to go
 *                      out as a real template or it isn't a preview).
 *   everything else  → WhatsAppDispatcher::sendRaw, which already routes
 *                      per-record on `provider`, so Unofficial API and
 *                      Twilio both land on their own path.
 *
 * Failure is never fatal: the schedule is already saved by the time we
 * run, and a preview that couldn't go out must not read as "your schedule
 * is broken". The caller folds our reason into its success message.
 */
class SchedulePreviewSender
{
    /**
     * @return array{ok: bool, reason?: string, to?: string}
     */
    public function send(ScheduledMessage $row, User $user): array
    {
        $to = preg_replace('/\D+/', '', (string) $user->mobile);
        if ($to === '' || strlen($to) < 8) {
            return ['ok' => false, 'reason' => 'no WhatsApp number on your account — add one in Account settings'];
        }

        $wsId   = (int) $row->workspace_id;
        $engine = strtolower((string) ($row->provider ?: ''));
        $tpl    = $row->template_id ? WaTemplate::find($row->template_id) : null;

        try {
            if ($tpl && $engine === 'waba') {
                return $this->sendWabaTemplate($row, $tpl, $to, $wsId);
            }
            return $this->sendRaw($row, $tpl, $to, $wsId, $user);
        } catch (\Throwable $e) {
            Log::warning('[SCHED-PREVIEW] threw', [
                'schedule_id' => $row->id, 'engine' => $engine, 'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Meta Cloud template send. Variables resolve against the operator as
     * if they were the recipient, so {{name}} shows THEIR name — the point
     * of a preview is seeing a filled-in message, not raw placeholders.
     */
    private function sendWabaTemplate(ScheduledMessage $row, WaTemplate $tpl, string $to, int $wsId): array
    {
        $vars = $this->varsFor($tpl, $to, $wsId, $row->template_overrides);

        $res = app(\App\Services\Waba\TemplateSender::class)->send($tpl, $to, $vars);
        if (($res['ok'] ?? false) === true) {
            return ['ok' => true, 'to' => $to];
        }
        return ['ok' => false, 'reason' => (string) ($res['error'] ?? 'Meta rejected the preview'), 'to' => $to];
    }

    /**
     * Unofficial API / Twilio / non-template WABA. The dispatcher resolves
     * the engine from `provider`, so one call covers them all.
     */
    private function sendRaw(ScheduledMessage $row, ?WaTemplate $tpl, string $to, int $wsId, User $user): array
    {
        // A template on a non-WABA engine renders down to text — the same
        // flattening the inbox mirror uses, so the preview reads exactly
        // like the bubble the recipient will get.
        $body = $tpl
            ? \App\Services\Inbox\InboxMirror::readableTemplateBody(TemplateDataBuilder::build($tpl, $wsId))
            : (string) $row->message_content;

        $body = trim($body);
        if ($body === '' && !$row->media_file) {
            return ['ok' => false, 'reason' => 'nothing to preview — the message body is empty'];
        }

        $res = app(\App\Services\WhatsAppDispatcher::class)->sendRaw([
            'from_number'  => $row->from_number,
            'to_number'    => $to,
            'body'         => $body,
            'media_path'   => $row->media_file ? 'uploads/scheduled/' . $row->media_file : null,
            'latitude'     => $row->latitude,
            'longitude'    => $row->longitude,
            'provider'     => $row->provider,
            'workspace_id' => $wsId,
        ], (int) $user->id, 'W');

        // `local_only` means the dispatcher stored a row but nothing left the
        // server (provider disabled, emergency halt, …) while still returning
        // ok=true. Counting that as a sent preview would tell the operator to
        // go check a phone that will never buzz.
        $reallySent = (($res['ok'] ?? false) === true) && (($res['local_only'] ?? false) !== true);
        if ($reallySent) {
            return ['ok' => true, 'to' => $to];
        }
        return [
            'ok'     => false,
            'reason' => (string) ($res['error'] ?? 'the sending service did not confirm the preview'),
            'to'     => $to,
        ];
    }

    /**
     * Build template variables for the operator-as-recipient. Delegates to
     * BroadcastsController::varsForRecipient so a preview substitutes the
     * exact same way the real send does — including this schedule's
     * send-time overrides. Same reflection hop campaigns and the scheduler
     * client already use; keeping it identical is deliberate, since a
     * private copy here is precisely how previews drift from reality.
     */
    private function varsFor(WaTemplate $tpl, string $to, int $wsId, $overrides): array
    {
        $user = auth()->user();
        $contact = [
            'id'                => 0,
            'phone'             => $to,
            'name'              => (string) ($user->name ?? ''),
            'first_name'        => (string) (explode(' ', trim((string) ($user->name ?? '')))[0] ?? ''),
            'last_name'         => '',
            'email'             => (string) ($user->email ?? ''),
            'mobile'            => $to,
            'custom_attributes' => [],
        ];

        $bcCtl = app(\App\Http\Controllers\BroadcastsController::class);
        $ref   = new \ReflectionClass($bcCtl);
        $varsM = $ref->getMethod('varsForRecipient');
        $varsM->setAccessible(true);

        return (array) $varsM->invoke($bcCtl, $tpl, $contact, $wsId, $overrides);
    }
}

<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ScheduledMessage;
use App\Models\WaTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Node.js scheduler service that actually fires WhatsApp messages.
 * Mirrors the endpoints the legacy controller used so the existing Node code
 * can run unchanged:
 *
 *   POST   {SERVER_URL}/api/schedule-message-bulk/{from_number}   (one-off)
 *   POST   {SERVER_URL}/api/schedule-recurring/{from_number}      (recurring)
 *   POST   {SERVER_URL}/api/pause-schedule/{node_schedule_id}     (pause)
 *   POST   {SERVER_URL}/api/resume-schedule/{node_schedule_id}    (resume)
 *   DELETE {SERVER_URL}/api/cancel-scheduled-message/{node_id}    (cancel/delete)
 *
 * Behavior on failure: log + return the original ScheduledMessage row. Callers
 * still apply the local DB state change so the user-facing UI stays in sync
 * even if the Node side is briefly unreachable. The next dispatcher tick (or
 * a manual retry) can re-attempt.
 *
 * SERVER_URL comes from the .env. If it's empty, every call short-circuits to
 * a no-op so dev installs without a Node side don't crash.
 */
class NodeSchedulerClient
{
    public function __construct(
        private ?string $baseUrl = null,
        private int $timeoutSeconds = 10,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? wd_node_url(), '/');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    /* ============================ register ============================ */

    public function registerOneOff(ScheduledMessage $scheduled, ?string $mediaUrl): ?string
    {
        if (!$this->isConfigured() || !$scheduled->from_number) return null;

        [$recipients, $recipientAttributes] = $this->resolveRecipients($scheduled);
        if (empty($recipients)) {
            $scheduled->markFailed('No recipients matched at registration time.');
            return null;
        }

        $templateData = $this->resolveTemplateData($scheduled);
        $templateData = $this->maybePrebuildMetaPayloads($scheduled, $templateData, $recipients, $recipientAttributes);

        // ISO-8601 with offset (e.g. `2026-05-16T11:45:00+00:00`) — Node
        // re-interprets via `moment.tz(value, userTz)`, which needs the
        // offset to convert UTC → user-local correctly. Plain
        // `toDateTimeString()` drops the offset and moment then treats
        // the naive string AS IF it were already in user-local time,
        // shifting every send by the user's UTC offset (we hit this
        // with 17:15 IST being re-parsed as 11:45 IST).
        $payload = [
            'scheduleId'         => $scheduled->id,
            // Authoritative engine for this row — Node MUST route off this,
            // not a workspace-level use_facebook_api lookup. The row's
            // provider was stamped at store-time from the picker the
            // operator chose, so trusting it here keeps Baileys-picked
            // sends out of the WABA fast-path even when the workspace
            // ALSO has a WABA config for the same phone (legacy mixed
            // setups). When this field is empty Node falls back to the
            // settings heuristic for backwards-compat with old rows.
            'provider'           => $scheduled->provider ?: null,
            'targetPhoneNumbers' => $recipients,
            'message'            => $this->resolveFreeformMessage($scheduled),
            'scheduleDateTime'   => optional($scheduled->scheduled_time)->toIso8601String(),
            'timezone'           => $scheduled->timezone,
            'messageType'        => $scheduled->template_type,
            'mediaUrl'           => $mediaUrl,
            'latitude'           => $scheduled->latitude,
            'longitude'          => $scheduled->longitude,
            'isTemplate'         => $templateData !== null,
            'templateData'       => $templateData,
            'recipientAttributes' => $recipientAttributes,
        ];

        return $this->post(
            "/api/schedule-message-bulk/{$scheduled->from_number}",
            $payload,
            $scheduled,
            'register-one-off',
        );
    }

    public function registerRecurring(ScheduledMessage $scheduled, ?string $mediaUrl): ?string
    {
        if (!$this->isConfigured() || !$scheduled->from_number) return null;

        // Snapshot recipients NOW so the Node side can iterate them on every
        // run without calling back to Laravel. The bot's recurring path
        // historically used `targetQueues` + fetchQueueRecipients; the new
        // schema doesn't have queues, so we hand the bot a pre-resolved
        // phone list as `targetPhoneNumbers`. The patched bot prefers that
        // list and falls back to fetchQueueRecipients only if it's empty.
        [$recipients, $recipientAttributes] = $this->resolveRecipients($scheduled);

        $templateData = $this->resolveTemplateData($scheduled);
        $templateData = $this->maybePrebuildMetaPayloads($scheduled, $templateData, $recipients, $recipientAttributes);

        $payload = [
            'scheduleId'         => $scheduled->id,
            // Engine authority — see registerOneOff for the why.
            'provider'           => $scheduled->provider ?: null,
            'targetPhoneNumbers' => $recipients,
            'recipientAttributes'=> $recipientAttributes,
            'targetQueues'       => $scheduled->target_queues ?? [],
            'targetGroups'       => $scheduled->target_groups ?? [],
            'targetNumbers'      => $scheduled->target_numbers ?? [],
            'message'            => $this->resolveFreeformMessage($scheduled),
            // ISO with offset — see comment on registerOneOff for why.
            'scheduleDateTime'   => optional($scheduled->scheduled_time)->toIso8601String(),
            'repeatInterval'     => $scheduled->repeat_interval,
            'repeatEvery'        => $scheduled->repeat_every,
            'daysOfWeek'         => $scheduled->days_of_week ?? [],
            'endDate'            => optional($scheduled->end_date)->toDateString(),
            'timezone'           => $scheduled->timezone,
            'messageType'        => $scheduled->template_type,
            'mediaUrl'           => $mediaUrl,
            'latitude'           => $scheduled->latitude,
            'longitude'          => $scheduled->longitude,
            'isTemplate'         => $scheduled->template_type === 'template',
            'templateData'       => $templateData,
        ];

        return $this->post(
            "/api/schedule-recurring/{$scheduled->from_number}",
            $payload,
            $scheduled,
            'register-recurring',
        );
    }

    /* ============================ lifecycle ============================ */

    public function pause(ScheduledMessage $scheduled): bool
    {
        return $this->lifecycleCall('post', "/api/pause-schedule/{$scheduled->node_schedule_id}", $scheduled, 'pause');
    }

    public function resume(ScheduledMessage $scheduled): bool
    {
        return $this->lifecycleCall('post', "/api/resume-schedule/{$scheduled->node_schedule_id}", $scheduled, 'resume');
    }

    public function cancel(ScheduledMessage $scheduled): bool
    {
        return $this->lifecycleCall('delete', "/api/cancel-scheduled-message/{$scheduled->node_schedule_id}", $scheduled, 'cancel');
    }

    /* ============================ internal ============================ */

    private function post(string $path, array $payload, ScheduledMessage $scheduled, string $op): ?string
    {
        try {
            $res = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                // Node gates its whole /api surface behind apiTokenGuard. On a
                // client server the Node URL is a public host (non-loopback), so
                // without this header the register call 401s and the schedule
                // never reaches the bot → node_schedule_id stays empty → it never
                // fires. Explicit token (not just the global Http middleware) so
                // scheduling works even on installs that predate that middleware.
                ->withHeaders(['X-Node-Token' => node_token()])
                ->post($this->baseUrl . $path, $payload);

            if (!$res->successful()) {
                Log::warning("NodeScheduler {$op} non-2xx", [
                    'schedule_id' => $scheduled->id,
                    'status'      => $res->status(),
                    'body'        => $res->body(),
                ]);
                return null;
            }

            $data = $res->json();
            return (string) ($data['scheduleId'] ?? $data['schedule_id'] ?? '') ?: null;
        } catch (\Throwable $e) {
            Log::error("NodeScheduler {$op} failed", [
                'schedule_id' => $scheduled->id,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function lifecycleCall(string $verb, string $path, ScheduledMessage $scheduled, string $op): bool
    {
        if (!$this->isConfigured() || !$scheduled->node_schedule_id) return false;
        try {
            $res = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                // Pause / resume / cancel hit the same token-gated Node /api —
                // without X-Node-Token these 401 on client servers, so the
                // lifecycle buttons silently no-op. See post() above.
                ->withHeaders(['X-Node-Token' => node_token()])
                ->{$verb}($this->baseUrl . $path);
            if (!$res->successful()) {
                Log::warning("NodeScheduler {$op} non-2xx", [
                    'schedule_id' => $scheduled->id,
                    'node_schedule_id' => $scheduled->node_schedule_id,
                    'status' => $res->status(),
                    'body'   => $res->body(),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::error("NodeScheduler {$op} failed", [
                'schedule_id' => $scheduled->id,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Returns [recipientNumbers[], recipientAttributesMap{}] for one-off sends.
     * The Node side wants a flat list of phone numbers + a per-number lookup
     * of contact fields so it can do "Hi {name}" substitution server-side.
     *
     * Public so the controller can also call it from the /api/scheduled/active
     * bot-sync endpoint.
     */
    public function resolveRecipients(ScheduledMessage $scheduled): array
    {
        $userIds = DB::table('workspace_user')
            ->where('workspace_id', $scheduled->workspace_id)
            ->pluck('user_id');

        $numbers = [];

        // Tag audience — resolved HERE, at fire time, not at schedule time.
        // That is the point: a contact tagged AFTER the schedule was created
        // still receives it, which is what makes "send to VIP every Monday"
        // behave the way an operator expects. Ids were workspace-checked when
        // the schedule was saved and are re-checked here.
        if ($scheduled->recipient_type === 'tag' && !empty($scheduled->target_groups)) {
            $tagIds = collect($scheduled->target_groups)
                ->map(fn ($g) => (int) str_replace('tag:', '', (string) $g))
                ->filter()->unique()->values();
            $safe = \App\Models\Tag::query()
                ->where('workspace_id', $scheduled->workspace_id)
                ->whereIn('id', $tagIds->all())
                ->pluck('id');
            if ($safe->isNotEmpty()) {
                Contact::query()
                    ->where('workspace_id', $scheduled->workspace_id)
                    ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $safe->all()))
                    ->get(['id', 'mobile'])
                    ->each(function ($c) use (&$numbers) {
                        if ($c->mobile) $numbers[] = (string) $c->mobile;
                    });
            }
        }

        if ($scheduled->recipient_type === 'group' && !empty($scheduled->target_groups)) {
            $wantedGroupIds = collect($scheduled->target_groups)->map(fn ($g) => (string) $g)->all();
            // contact_group is encrypted JSON in the Contact model — must hydrate.
            $contacts = Contact::whereIn('user_id', $userIds)->get();
            foreach ($contacts as $c) {
                $own = collect($c->contact_group ?? [])->map(fn ($v) => (string) $v)->all();
                if (array_intersect($own, $wantedGroupIds)) {
                    $numbers[] = (string) $c->mobile;
                }
            }
        }

        if ($scheduled->recipient_type === 'queue' && !empty($scheduled->target_queues)) {
            // "queue" = past broadcast(s). Re-target their recipients via
            // broadcast_contacts → contacts.mobile. Only queues owned by a
            // member of this workspace are accepted.
            $broadcastIds = DB::table('broadcasts')
                ->whereIn('id', $scheduled->target_queues)
                ->whereIn('user_id', $userIds)
                ->pluck('id');
            if ($broadcastIds->isNotEmpty()) {
                $contactIds = DB::table('broadcast_contacts')
                    ->whereIn('broadcast_id', $broadcastIds)
                    ->distinct('contact_id')
                    ->pluck('contact_id');
                if ($contactIds->isNotEmpty()) {
                    $numbers = Contact::whereIn('id', $contactIds)
                        ->get()->pluck('mobile')->filter()->map(fn ($m) => (string) $m)->all();
                }
            }
        }

        if ($scheduled->recipient_type === 'number' && !empty($scheduled->target_numbers)) {
            $numbers = $scheduled->target_numbers;
        }

        $numbers = array_values(array_filter(array_unique($numbers), fn ($n) => $n !== null && $n !== ''));

        // RESUME-SAFE: drop recipients this schedule ALREADY sent to (status
        // 'sent' in scheduled_message_contacts). On a retry/resume the bridge
        // must target only the UNSENT remainder — never re-blast people who
        // already got it. First send is unaffected (no 'sent' rows yet).
        // Digits-only match to dodge phone-formatting differences.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('scheduled_message_contacts')) {
                $sent = DB::table('scheduled_message_contacts')
                    ->where('scheduled_message_id', $scheduled->id)
                    ->where('status', 'sent')
                    ->pluck('phone')
                    ->map(fn ($p) => preg_replace('/\D+/', '', (string) $p))
                    ->filter()->flip()->all();
                if (!empty($sent)) {
                    $numbers = array_values(array_filter(
                        $numbers,
                        fn ($n) => !isset($sent[preg_replace('/\D+/', '', (string) $n)])
                    ));
                }
            }
        } catch (\Throwable $e) {
            // Table missing / query failed → fall back to full audience.
        }

        // Build the per-number attribute map. Encrypted columns force a hydrate.
        $contactsByMobile = Contact::whereIn('user_id', $userIds)->get()
            ->mapWithKeys(fn ($c) => [(string) $c->mobile => $c]);

        $attrs = [];
        foreach ($numbers as $n) {
            $c = $contactsByMobile->get((string) $n);
            $attrs[$n] = $c ? [
                'name'         => $c->name,
                'first_name'   => $c->first_name,
                'last_name'    => $c->last_name,
                'mobile'       => $c->mobile,
                'email'        => $c->email,
                'address'      => $c->address,
                'title'        => $c->title,
                'country_code' => $c->country_code,
                'language'     => $c->language,
            ] : [];
        }

        return [$numbers, $attrs];
    }

    /**
     * Build the templateData payload Node consumes when firing a
     * scheduled send. Includes EVERY field Node's broadcastService /
     * scheduleService can render — body/header/footer for plain
     * templates, attachment_* for media+caption sends, buttons for
     * CTA templates, carousel_data for swipeable cards.
     *
     * Workspace-level attributes ({{promo_key}}, {{order_id}}, etc.)
     * are pre-substituted here via App\Services\AttributeResolver —
     * those are constant across recipients so we bake them in once.
     * Contact-level placeholders ({{name}}, {{phone}}, {{email}}) are
     * left intact for Node's per-recipient pass.
     *
     * Public so the controller can call it from /api/scheduled/active when
     * the bot re-registers schedules after a restart and needs the original
     * template content (the message_content column is empty for template-type
     * sends since the body comes from wa_templates).
     */
    /**
     * Pre-build the full Meta `type:template` payload per recipient
     * so Node can ship it verbatim to Graph instead of running its
     * own partial builder. Same pattern as broadcasts. Keyed by phone
     * (scheduled-message recipients are phone strings, not Contact
     * rows, so we can't key by contact_id).
     *
     * Only runs when:
     *   - templateData exists (this is a template send)
     *   - the template has been submitted to Meta + APPROVED
     *   - waba_templates_v2_enabled is on
     *
     * Otherwise returns templateData untouched and Node's existing
     * Baileys/partial path handles the send (which is fine for
     * Baileys workspaces).
     */
    /**
     * Mirrors BroadcastsController::mediaUrlReachableForMeta. Refuses
     * media URLs Meta cannot fetch (http://, private IPs, .local /
     * .test / .localhost). Returns null when OK, error string when not.
     */
    private function mediaUrlReachableForMeta(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return "Media URL '$url' is invalid.";
        $scheme = strtolower($parts['scheme'] ?? 'http');
        if ($scheme !== 'https') {
            return "Media URL must be HTTPS for Meta to fetch it (got: {$scheme}). Configure APP_URL with an https:// public domain.";
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPrivate = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($isPrivate) return "Media URL host '$host' is a private/reserved IP. Meta cannot reach it.";
        } else {
            foreach (['.local', '.test', '.internal', '.localhost'] as $bad) {
                if (str_ends_with($host, $bad)) return "Media URL host '$host' ends with $bad which Meta cannot resolve.";
            }
            if ($host === 'localhost') return "Media URL host is 'localhost'. Meta cannot reach it.";
        }
        return null;
    }

    private function maybePrebuildMetaPayloads(
        ScheduledMessage $scheduled,
        ?array $templateData,
        array $recipients,
        array $recipientAttributes
    ): ?array {
        if (!$templateData) return $templateData;
        if (!$scheduled->template_id) return $templateData;

        $tpl = WaTemplate::find($scheduled->template_id);
        if (!$tpl || !$tpl->meta_template_id) return $templateData;
        if (strtoupper((string) $tpl->meta_status) !== 'APPROVED') return $templateData;
        if (!\App\Models\SystemSetting::get('waba_templates_v2_enabled', false)) return $templateData;

        // Refuse auth templates — same reason as broadcasts/campaigns.
        // Random per-recipient OTPs that no backend can verify = useless.
        if ($tpl->template_type === 'auth') {
            $scheduled->markFailed('Authentication templates cannot be scheduled — each recipient needs a unique verifiable OTP code. Send 1:1 via your backend instead.');
            return $templateData;
        }

        // Quality / paused gates — refuse before burning Meta quota.
        if ($tpl->paused_until && $tpl->paused_until->isFuture()) {
            $scheduled->markFailed('Template is paused until ' . $tpl->paused_until->format('Y-m-d H:i') . ' due to negative customer feedback.');
            return $templateData;
        }
        $floor = strtoupper((string) \App\Models\SystemSetting::get('waba_template_quality_floor', 'YELLOW'));
        $rank  = ['UNKNOWN' => 1, 'RED' => 0, 'YELLOW' => 2, 'GREEN' => 3];
        $score = strtoupper((string) ($tpl->quality_score ?: 'UNKNOWN'));
        if (($rank[$score] ?? 1) < ($rank[$floor] ?? 2)) {
            $scheduled->markFailed("Template quality is {$score} (floor: {$floor}). Schedule blocked to prevent further quality drop.");
            return $templateData;
        }

        // HTTPS media URL guard — Meta cannot fetch http://, private
        // IPs, or .local/.test/.localhost hosts. Refuse early.
        if (!empty($tpl->attachment_file)
            && !empty($tpl->attachment_type)
            && !in_array(strtoupper((string) $tpl->attachment_type), ['NONE', 'TEXT', 'LOCATION'], true)) {
            $url = media_url($tpl->attachment_file);
            $err = $this->mediaUrlReachableForMeta($url);
            if ($err) {
                $scheduled->markFailed($err);
                return $templateData;
            }
        }

        $bcCtl = app(\App\Http\Controllers\BroadcastsController::class);
        $ref   = new \ReflectionClass($bcCtl);
        $varsM = $ref->getMethod('varsForRecipient'); $varsM->setAccessible(true);
        $wrapM = $ref->getMethod('wrapUrlsForRecipient'); $wrapM->setAccessible(true);
        $builder = new \App\Services\Waba\TemplatePayloadBuilder();

        $payloadsByPhone = [];
        foreach ($recipients as $phone) {
            $attrs = $recipientAttributes[$phone] ?? [];
            // Synthesize a contact-like array from the recipientAttributes
            // payload. Scheduled messages don't carry contact_id refs,
            // so per-recipient LinkTracker context falls back to phone.
            $contact = [
                'id'                => 0,
                'phone'             => $phone,
                'first_name'        => (string) ($attrs['first_name'] ?? ''),
                'last_name'         => (string) ($attrs['last_name']  ?? ''),
                'name'              => (string) ($attrs['name']       ?? ''),
                'email'             => (string) ($attrs['email']      ?? ''),
                'custom_attributes' => is_array($attrs) ? $attrs : [],
            ];

            try {
                // 4th arg = this schedule's send-time overrides (NULL for
                // every pre-existing row → identical behaviour).
                $vars = $varsM->invoke($bcCtl, $tpl, $contact, (int) $scheduled->workspace_id, $scheduled->template_overrides);
                $vars = $wrapM->invoke($bcCtl, $vars, [
                    'workspace_id' => (int) $scheduled->workspace_id,
                    'template_id'  => $tpl->id,
                    'phone'        => $phone,
                ]);
                $payloadsByPhone[$phone] = $builder->buildSend($tpl, $vars);
            } catch (\Throwable $e) {
                \Log::warning('[SCHED] meta_payload build failed for recipient', [
                    'phone'    => $phone,
                    'tpl'      => $tpl->id,
                    'err'      => $e->getMessage(),
                ]);
            }
        }

        $templateData['meta_payloads_by_phone'] = $payloadsByPhone;
        \Log::info('[SCHED] meta_payloads_by_phone built', [
            'scheduled_id' => $scheduled->id,
            'count'        => count($payloadsByPhone),
            'tpl'          => $tpl->id,
        ]);
        return $templateData;
    }

    /**
     * Resolve workspace-level attributes ({{promo_key}}, {{order_id}},
     * positional {{1}}) into a freeform (non-template) scheduled body
     * BEFORE it ships to Node. Node's scheduleService only does
     * per-recipient CONTACT substitution ({{name}}, {{email}}, …) and
     * has no knowledge of the workspace `attributes` table — so without
     * this pass a freeform scheduled message would deliver a literal
     * {{promo_key}} to the customer. Contact placeholders are left
     * intact for Node's per-recipient pass. Mirrors the resolution that
     * resolveTemplateData / BroadcastsController::buildTemplateData do
     * for template bodies.
     */
    public function resolveFreeformMessage(ScheduledMessage $scheduled): ?string
    {
        $body = (string) ($scheduled->message_content ?? '');
        if ($body === '' || !str_contains($body, '{{')) {
            return $scheduled->message_content;
        }
        // Template-type rows carry their body in wa_templates, not here.
        if ($scheduled->template_id) return $scheduled->message_content;

        return app(\App\Services\AttributeResolver::class)
            ->resolve($body, [], (int) $scheduled->workspace_id);
    }

    public function resolveTemplateData(ScheduledMessage $scheduled): ?array
    {
        if (!$scheduled->template_id) return null;

        $template = WaTemplate::find($scheduled->template_id);
        if (!$template) return null;

        $resolver = app(\App\Services\AttributeResolver::class);
        $wsId     = (int) $scheduled->workspace_id;

        // variable_map decides how positional {{1}} {{2}} placeholders
        // map to attribute keys. Decode once and pass into the resolver
        // so it can satisfy positional substitutions.
        $variableMap = $template->variable_map ?? null;
        if (is_string($variableMap)) {
            $decoded = json_decode($variableMap, true);
            $variableMap = is_array($decoded) ? $decoded : [];
        }
        $variableMap = is_array($variableMap) ? $variableMap : [];

        // The resolver leaves contact-level placeholders untouched (no
        // key in the workspace `attributes` table for {{name}} etc.),
        // so Node still gets to do its per-recipient substitution.
        $body   = $resolver->resolve((string) ($template->template_body ?? ''), $variableMap, $wsId) ?: null;
        $header = $resolver->resolve((string) ($template->header        ?? ''), $variableMap, $wsId) ?: null;
        $footer = $resolver->resolve((string) ($template->footer        ?? ''), $variableMap, $wsId) ?: null;

        // Carousel cards each have their own title/body/footer that we
        // need to resolve too (otherwise card text ships with raw
        // {{promo_key}} placeholders).
        $carousel = $template->carousel_data ?? null;
        if (is_string($carousel)) {
            $decoded = json_decode($carousel, true);
            $carousel = is_array($decoded) ? $decoded : null;
        }
        if (is_array($carousel)) {
            $carousel = array_map(function ($card) use ($resolver, $variableMap, $wsId) {
                if (!is_array($card)) return $card;
                foreach (['title', 'body', 'footer'] as $field) {
                    if (isset($card[$field]) && is_string($card[$field])) {
                        $card[$field] = $resolver->resolve($card[$field], $variableMap, $wsId);
                    }
                }
                return $card;
            }, $carousel);
        }

        $buttons = $template->buttons ?? [];
        if (is_string($buttons)) {
            $decoded = json_decode($buttons, true);
            $buttons = is_array($decoded) ? $decoded : [];
        }

        // attachment_file is the storage-relative path produced by
        // `$file->store('wa-templates', 'public')` in
        // TemplatesController, e.g. `wa-templates/abc.jpg`. The
        // public URL is `app/storage/wa-templates/abc.jpg`. (Pre-fix
        // this used the legacy `uploads/templates/attachments/` path
        // which 404'd, so Node had no media to download.)
        $attachmentUrl = null;
        if (!empty($template->attachment_file)) {
            $attachmentUrl = media_url($template->attachment_file);
        }

        // Base64-inline the attachment ONCE per scheduled job so the Node
        // scheduler never downloads media per recipient (which silently
        // dropped the image when Node couldn't reach the storage URL).
        // attachment_url stays as the network fallback.
        $inlineMedia = \App\Models\WaTemplate::inlineAttachment($template->attachment_file);

        return [
            'id'              => $template->id,
            'template_name'   => $template->template_name ?? null,
            'template_type'   => $template->template_type ?? 'standard',
            'category'        => $template->category ?? null,
            'language'        => $template->language ?? null,
            // Both header and title_text — legacy Node code reads
            // title_text, the new path reads header.
            'header'          => $header,
            'title_text'      => $header,
            'template_body'   => $body,
            'footer'          => $footer,
            'buttons'         => $buttons,
            'attachment_type'    => $template->attachment_type ?? null,
            'attachment_file'    => $template->attachment_file ?? null,
            'attachment_url'     => $attachmentUrl,
            'attachment_base64'  => $inlineMedia['attachment_base64'],
            'attachment_mime'    => $inlineMedia['attachment_mime'],
            'carousel_data'      => $carousel,
            // Raw variable_map for Node's positional → named hop so
            // {{1}} still resolves at send time even if PHP didn't
            // bake it in (contact-level positional, e.g. {{1}} → name).
            'variable_map'       => $variableMap,
            // Twilio Content SID — `scheduleService.js` uses this in
            // its Twilio branch (`sendTwilioBulkMessage`). Without it
            // every scheduled Twilio send fell back to plain Body
            // regardless of whether the template had a ContentSid.
            'twilio_content_sid' => $template->twilio_content_sid ?: null,
        ];
    }
}

<?php

namespace App\Services\Inbox;

use App\Models\KeywordReply;
use App\Models\KeywordReplyLog;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\Contact;
use App\Services\InboxDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

/**
 * /auto-reply matcher + dispatcher for WABA Cloud + Twilio inbound paths.
 *
 * Background: the original keyword_replies system only fired through Node
 * (BaileysClientManager.checkKeywordReply → /api/keyword-replies →
 * AutoReplyController::lookup). That endpoint is consumed by Node only,
 * so WABA + Twilio inbound webhooks completely bypassed the operator's
 * /auto-reply rules — the UI was a silent no-op on those providers.
 *
 * This service is invoked from `WaWebhookController::captureInboundMessage`
 * after `RoutingEngine::applyToInbound`. It runs the SAME keyword match
 * the Baileys/Node path uses (scoped on the workspace's `provider`),
 * enforces the per-contact cooldown the Node path forgot to enforce,
 * applies an anti-loop / fromMe guard, substitutes contact variables,
 * and ships the reply via `InboxDispatcher` so each provider's correct
 * code path is exercised (Meta Cloud, Twilio Content API, etc).
 *
 * Baileys is unchanged — Node still calls the old endpoint directly.
 */
class KeywordReplyDispatcher
{
    /**
     * Per-contact cooldown floor — even when an individual rule sets
     * `cooldown=0` we never re-fire to the SAME contact within this
     * window. Protects against the "customer pastes the same keyword
     * 20× in a row" flood path that the old Node-only path missed.
     */
    private const HARD_FLOOR_SECONDS = 30;

    public function maybeDispatch(
        Conversation $convo,
        string $body,
        string $contactPhone,
        ?string $selfNumber = null,
        ?string $igTrigger = null
    ): ?KeywordReply
    {
        $body = trim((string) $body);

        // Instagram-native trigger context passed by the IG webhook ingest:
        //   dm_keyword    — keyword-match the DM (default; same as WhatsApp)
        //   story_reply   — keyword-match a reply to the account's story
        //   story_mention — fire on ANY story mention (no keyword text)
        //   mention       — fire on ANY @mention (no keyword text)
        // NULL on the WhatsApp path → behaviour below is byte-for-byte unchanged.
        $igNoKeyword = $igTrigger !== null
            && in_array($igTrigger, KeywordReply::IG_TRIGGERS_NO_KEYWORD, true);

        // A keyword-bearing trigger with no text has nothing to match; the two
        // no-keyword IG triggers legitimately arrive with an empty body.
        if ($body === '' && ! $igNoKeyword) return null;

        $workspaceId = (int) ($convo->workspace_id ?? 0);
        if ($workspaceId <= 0) return null;

        // Meta Business Agent coexistence — if Meta's agent is fronting this
        // workspace's WhatsApp, suppress OUR keyword auto-replies so the
        // customer never gets two answers.
        $ws = \App\Models\Workspace::find($workspaceId);
        if ($ws && $ws->suppressesOurAutoReply()) {
            \Log::info('[KEYWORD] skipped — Meta Business Agent is fronting this workspace', [
                'workspace_id' => $workspaceId,
            ]);
            return null;
        }

        // Provider scope — a WABA workspace shouldn't fire a rule that
        // was created while the workspace was on Baileys. The /auto-reply
        // form should already stamp `provider` on save, but defence-in-
        // depth in the matcher matches what /broadcasts, /campaigns and
        // /scheduled all do.
        $provider = strtolower((string) ($convo->provider ?? ''))
            ?: \App\Services\WorkspaceEngine::for($workspaceId);

        // Anti-loop guard. If the inbound `to_number` and `from` match
        // the workspace's own connected phone (i.e. our own outbound
        // mirrored back as an inbound, or operator's mobile typing on
        // the same WABA number), refuse. Echo-of-our-own-auto-reply →
        // re-trigger storm is the worst failure mode here.
        // `conversations` table has no `device_phone` column — resolve
        // the workspace's own number from the explicit `$selfNumber`
        // arg (WABA + Twilio webhook controllers pass it from the
        // payload's `to_number`) and fall back to the linked Device
        // relation for Baileys conversations.
        $needleDigits = preg_replace('/\D+/', '', $contactPhone);
        $devicePhone  = (string) ($selfNumber ?? '');
        if ($devicePhone === '' && !empty($convo->device_id)) {
            try {
                $dev = \App\Models\Device::find($convo->device_id);
                if ($dev) $devicePhone = (string) ($dev->phone_number ?? '');
            } catch (\Throwable $e) {}
        }
        $selfDigits = preg_replace('/\D+/', '', $devicePhone);
        if ($needleDigits !== '' && $selfDigits !== '' && $needleDigits === $selfDigits) {
            Log::debug('[AR-DISPATCH] skipped: self-echo', [
                'workspace_id' => $workspaceId,
                'phone'        => $needleDigits,
            ]);
            return null;
        }

        // Flow-builder inbound-condition triggers — launch `away` / `out_of_hours`
        // flows (idempotent per contact) independent of any auto-reply RULE below.
        // Fast-paths out immediately when the workspace is in-hours and not away.
        try {
            app(\App\Services\Flow\FlowEnrollmentService::class)
                ->onInboundMessage($workspaceId, $needleDigits);
        } catch (\Throwable $e) {
            Log::warning('[AR-DISPATCH] inbound flow trigger failed: ' . $e->getMessage());
        }

        // ── Shared auto-responder gates + contact resolver ─────────────────
        $evaluator = app(AutoResponderEvaluator::class);

        // Memoised contact resolver — `contacts.mobile` is encrypted, so we
        // decrypt-scan the workspace ONCE and reuse the hit for exclusion +
        // reply-text substitution across both the welcome and keyword passes.
        $contactBox = ['done' => false, 'val' => null];
        $getContact = function () use (&$contactBox, $workspaceId, $needleDigits) {
            if ($contactBox['done']) return $contactBox['val'];
            $contactBox['done'] = true;
            Contact::query()->where('workspace_id', $workspaceId)->orderByDesc('id')
                ->chunk(200, function ($chunk) use (&$contactBox, $needleDigits) {
                    foreach ($chunk as $c) {
                        if (preg_replace('/\D+/', '', (string) $c->mobile) === $needleDigits) {
                            $contactBox['val'] = $c;
                            return false;
                        }
                    }
                    return true;
                });
            return $contactBox['val'];
        };

        $guard = app(AutoReplyGuard::class);

        // ── WELCOME MESSAGE pass ───────────────────────────────────────────
        // A welcome rule greets the customer on a NEW conversation (first
        // message, or after the "Resend after" window) — no keyword needed, and
        // it runs BEFORE keyword matching so a greeting fronts the thread. All
        // gating (excluded / working-hours / agent-override / resend) lives in
        // AutoResponderEvaluator so the Baileys lookup() path decides identically.
        try {
            $welcomeRules = KeywordReply::query()
                ->welcomeFor($workspaceId, $provider)
                // Sender scoping — a device-bound welcome fires only for that
                // sender; a workspace-wide one (device_id null) fires for any.
                ->where(function ($q) use ($convo) {
                    $q->whereNull('device_id');
                    if (! empty($convo->device_id)) {
                        $q->orWhere('device_id', (int) $convo->device_id);
                    }
                })
                ->with(['selectedContents', 'outsideHoursContents', 'flow'])
                ->orderBy('id')->get();
            foreach ($welcomeRules as $wr) {
                $v = $evaluator->evaluateWelcome($wr, $convo, $needleDigits, $getContact());
                if (! $v['fire']) continue;
                Log::info('[AR-WELCOME] firing', [
                    'rule' => $wr->id, 'ws' => $workspaceId, 'outside' => $v['use_outside'], 'reason' => $v['reason'],
                ]);
                $res = $this->shipRuleReply($wr, $convo, $needleDigits, $getContact(), $guard, (bool) $v['use_outside']);
                // Stamp the per-contact fire time so the resend window throttles
                // the next inbound (ships only once, then re-greets after the window).
                if ($res) {
                    $evaluator->stampFire($wr, $convo);
                }
                return $res;
            }
        } catch (\Throwable $e) {
            Log::warning('[AR-WELCOME] pass failed: ' . $e->getMessage(), ['ws' => $workspaceId]);
        }

        // Sender scope shared by the away + out-of-hours passes: a device-bound
        // rule fires only for its own sender; a workspace-wide one (device_id
        // null) fires for any inbound on this workspace.
        $senderScope = function ($q) use ($convo) {
            $q->whereNull('device_id');
            if (! empty($convo->device_id)) {
                $q->orWhere('device_id', (int) $convo->device_id);
            }
        };

        // ── AWAY pass ──────────────────────────────────────────────────────
        // Fires on ANY inbound while the workspace's manual "Away mode" switch is
        // ON — like WhatsApp Business away messages. A deliberate operator signal,
        // so it fronts the automatic out-of-hours pass below. One workspace lookup,
        // reused across every away rule (and passed to the evaluator to skip a
        // second query per rule).
        try {
            $isAway = $evaluator->isWorkspaceAway($workspaceId);
            if ($isAway) {
                $awayRules = KeywordReply::query()
                    ->awayFor($workspaceId, $provider)
                    ->where($senderScope)
                    ->with(['selectedContents', 'outsideHoursContents', 'flow'])
                    ->get();
                foreach ($awayRules as $ar) {
                    $v = $evaluator->evaluateAway($ar, $convo, $needleDigits, $getContact(), $isAway);
                    if (! $v['fire']) continue;
                    $res = $this->shipRuleReply($ar, $convo, $needleDigits, $getContact(), $guard, false);
                    if ($res) {
                        Log::info('[AR-AWAY] firing', ['rule' => $ar->id, 'ws' => $workspaceId]);
                        $evaluator->stampFire($ar, $convo);
                        return $res;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[AR-AWAY] pass failed: ' . $e->getMessage(), ['ws' => $workspaceId]);
        }

        // ── OUT-OF-HOURS pass ──────────────────────────────────────────────
        // Fires on ANY inbound that arrives OUTSIDE the rule's own working_hours
        // (no keyword). The rule's schedule defines "open"; the evaluator only
        // fires it when we are NOT within those hours (and never when no hours are
        // configured, so it can't become an always-on responder).
        try {
            $oohRules = KeywordReply::query()
                ->outOfHoursFor($workspaceId, $provider)
                ->where($senderScope)
                ->with(['selectedContents', 'outsideHoursContents', 'flow'])
                ->get();
            foreach ($oohRules as $or) {
                $v = $evaluator->evaluateOutOfHours($or, $convo, $needleDigits, $getContact());
                if (! $v['fire']) continue;
                $res = $this->shipRuleReply($or, $convo, $needleDigits, $getContact(), $guard, false);
                if ($res) {
                    Log::info('[AR-OOH] firing', ['rule' => $or->id, 'ws' => $workspaceId]);
                    $evaluator->stampFire($or, $convo);
                    return $res;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[AR-OOH] pass failed: ' . $e->getMessage(), ['ws' => $workspaceId]);
        }

        // Per-conversation cooldown gate via AutoReplyGuard — this is
        // the SAME gate the RoutingEngine path uses, so a contact
        // triggering both auto-reply paths is rate-limited globally.
        // The guard reads `conversations.routing_meta.last_auto_reply_at`
        // — markReplied() writes that field after each successful fire
        // below, so the next call to canAutoReply() on the same convo
        // refuses within the cooldown window. We can't enforce a
        // per-contact hard floor on the encrypted keyword_reply_logs
        // table (each row's contact_phone uses a random IV — no WHERE
        // match possible), so this guard is the canonical cooldown.
        $guard = null;
        try {
            $guard = app(AutoReplyGuard::class);
            if ($guard && method_exists($guard, 'canAutoReply') && !$guard->canAutoReply($convo)) {
                return null;
            }
        } catch (\Throwable $e) {
            Log::debug('[AR-DISPATCH] AutoReplyGuard threw: ' . $e->getMessage());
        }

        // Candidate rules scoped to workspace + provider + active.
        $candidates = KeywordReply::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', true)
            ->where(function ($q) use ($provider) {
                // Tolerate rows with NULL/empty provider (pre-migration)
                // so legacy data still fires on the new path.
                $q->where('provider', $provider)
                  ->orWhereNull('provider')
                  ->orWhere('provider', '');
            });

        // Instagram trigger scoping. Only constrains when the IG webhook passed a
        // trigger context; the WhatsApp path (and IG calls with no context) fall
        // through unchanged. A story_reply rule must NOT fire on a plain DM, and a
        // dm_keyword DM must NOT trip a story/mention rule — so scope by ig_trigger.
        if ($igTrigger !== null) {
            $candidates->where(function ($q) use ($igTrigger) {
                $q->where('ig_trigger', $igTrigger);
                // Legacy IG rules saved before this feature have ig_trigger=NULL —
                // treat them as dm_keyword so they keep firing exactly as before.
                if ($igTrigger === 'dm_keyword') {
                    $q->orWhereNull('ig_trigger')->orWhere('ig_trigger', '');
                }
            });
        } else {
            // No IG context (WhatsApp, or IG without a resolved trigger): never
            // let an IG story/mention rule fire on the generic keyword path.
            $candidates->where(function ($q) {
                $q->whereNull('ig_trigger')->orWhere('ig_trigger', '')->orWhere('ig_trigger', 'dm_keyword');
            });
        }

        // No-keyword IG triggers (story_mention / mention) fire on ANY event of
        // their kind — skip keyword matching entirely; keyword-bearing triggers
        // keep the exact keyword-match behaviour.
        if (! $igNoKeyword) {
            $candidates->matchKeyword($body);
        }

        $candidates = $candidates->with(['selectedContents'])->get();

        $rule = $igNoKeyword
            ? $candidates->first()
            : $candidates->first(fn ($c) => $c->matchesNeedle($body));
        if (!$rule) return null;

        // Rule-level cooldown — its own row says "min N seconds between
        // fires of this specific rule per contact". 0 means rely on the
        // hard floor above only.
        $perRuleCooldown = max(0, (int) ($rule->cooldown ?? 0));
        if ($perRuleCooldown > 0) {
            $existed = KeywordReplyLog::query()
                ->where('keyword_reply_id', $rule->id)
                ->where('contact_phone', $needleDigits)
                ->where('created_at', '>=', now()->subSeconds($perRuleCooldown))
                ->exists();
            if ($existed) return null;
        }

        // Resolve the contact (memoised decrypt-scan shared with the welcome
        // pass) for placeholder substitution + the auto-responder gates below.
        $contact = $getContact();

        // Auto-responder gates on a matched KEYWORD rule — all OPT-IN, so a
        // classic keyword rule (these columns empty) is completely unaffected:
        //   • excluded recipients   • agent-override pause   • per-rule working hours
        if ($evaluator->isExcluded($rule, $needleDigits, $contact)) {
            return null;
        }
        if ($evaluator->agentPaused($rule, $convo, $needleDigits)) {
            return null;
        }
        $wh = $evaluator->workingHours($rule);
        if (! $wh['in']) {
            // Outside working hours → send the dedicated outside-hours message,
            // or stay silent, per the operator's Step-3 choice.
            if ($wh['outside_action'] === 'send') {
                return $this->shipRuleReply($rule, $convo, $needleDigits, $contact, $guard, true);
            }
            return null;
        }

        $replyText = $this->resolveReplyText($rule, $contact, $needleDigits);

        // Reply-type aware dispatch. The Node/Baileys path branches on
        // reply_type in BaileysClientManager; the WABA/Twilio path used to
        // handle ONLY plain text, so flow/contact/catalog/location/media/
        // template rules were silent no-ops on those engines. Mirror the
        // full set here so all three engines behave identically.
        $replyType = strtolower((string) ($rule->reply_type ?? 'custom')) ?: 'custom';

        // `timeout` doubles as the per-rule reply delay for non-flow
        // replies (the same column the Node path surfaces as reply_delay).
        // Capped to 30s on this path because we pace inside
        // app()->terminating(), which holds the PHP worker; the async Node
        // path can afford the full 300s.
        $delaySeconds = $replyType === 'flow' ? 0 : max(0, min(30, (int) ($rule->timeout ?? 0)));

        // FLOW — enrol the contact into the selected flow. The flow runtime
        // (flowService.js) is already WABA/Baileys/Twilio-aware, so this is
        // the full implementation, not a text fallback.
        if ($replyType === 'flow') {
            $flow = $rule->flow_id ? \App\Models\Flow::find($rule->flow_id) : null;
            if ($flow) {
                $enrollContact = $contact ?: $this->findOrMakeContact($workspaceId, $convo, $needleDigits);
                if ($enrollContact) {
                    try {
                        app(\App\Services\Flow\FlowEnrollmentService::class)->enroll($enrollContact, $flow);
                    } catch (\Throwable $e) {
                        Log::warning('[AR-DISPATCH] flow enrol failed: ' . $e->getMessage(), [
                            'rule_id' => $rule->id, 'flow_id' => $flow->id,
                        ]);
                    }
                }
            }
            $this->recordFire($rule, $needleDigits, $body, $guard, $convo);
            return $rule;
        }

        // Build the outbound bubble for every non-flow reply type, then let
        // InboxDispatcher route it to the right transport.
        $im = new InboxMessage();
        $im->conversation_id = $convo->id;
        $im->user_id         = $convo->user_id ?? null;
        $im->to_number       = $needleDigits;
        $im->direction       = 'out';
        $im->status          = 'pending';
        $meta = [
            'auto_reply_id'      => $rule->id,
            'auto_reply_keyword' => $rule->keyword,
            'source'             => 'auto_reply',
        ];

        $shipped = false;
        if ($replyType === 'share_contact') {
            $target = $rule->target_contact_id ? Contact::find($rule->target_contact_id) : null;
            if ($target && (int) ($target->workspace_id ?? 0) === $workspaceId) {
                $tName = $target->name
                    ?: (trim(($target->first_name ?? '') . ' ' . ($target->last_name ?? '')) ?: 'Contact');
                $tNum = preg_replace('/\D+/', '', (string) ($target->country_code . $target->mobile))
                    ?: preg_replace('/\D+/', '', (string) $target->mobile);
                // InboxDispatcher has no native contact-card builder for
                // WABA/Twilio — ship a clean readable text card so the
                // reply still lands on every engine.
                $im->body = trim($tName . ($tNum !== '' ? "\n+" . $tNum : ''));
                $shipped  = $im->body !== '';
            }
        } elseif ($replyType === 'send_catalog') {
            $im->body = $replyText !== '' ? $replyText : 'Thanks! Our product catalog is on its way to you shortly.';
            $shipped  = true;
        } elseif ($replyType === 'request_location') {
            $im->body = $replyText !== '' ? $replyText : 'Could you please share your location so we can help you better?';
            $shipped  = true;
        } else {
            // custom — text / media / template, from the first selected content.
            $content = method_exists($rule, 'selectedContents') ? $rule->selectedContents->first() : null;
            $ctype   = strtolower((string) ($content?->content_type ?? $rule->message_type ?? 'text'));

            if ($ctype === 'template' && $content && $content->template_id) {
                // Proper template send: WABA emits type:template, Twilio
                // emits the registered ContentSid (both read meta.template_*).
                $tpl = \App\Models\WaTemplate::find((int) $content->template_id);
                if ($tpl && (int) ($tpl->workspace_id ?? $workspaceId) === $workspaceId) {
                    $meta['template_id']       = $tpl->id;
                    $meta['template_name']     = (string) ($tpl->name ?? $tpl->template_name ?? '');
                    $meta['template_language'] = (string) ($tpl->language ?? $tpl->lang ?? 'en');
                    $im->body = $replyText !== '' ? $replyText : (string) ($tpl->body ?? $tpl->template_body ?? '');
                    $shipped  = true;
                }
            } elseif (in_array($ctype, ['image', 'video', 'document'], true) && $content && $content->file_path) {
                // Media reply — InboxDispatcher ships media on all 3 engines.
                $im->media_path = $content->file_path;
                $im->media_type = $ctype;
                $im->body       = $replyText; // caption (may be '')
                $shipped        = true;
            } else {
                if ($replyText !== '') {
                    $im->body = $replyText;
                    $shipped  = true;
                }
            }
        }

        if (!$shipped) return null;

        try {
            $im->meta = array_filter($meta, fn ($v) => $v !== null && $v !== '');
            $im->save();
            // Pace + dispatch. Delay>0 defers the actual transport call to
            // app()->terminating() so the provider webhook returns instantly
            // (no retry storm) while still honouring the human-like delay.
            $this->shipInbox($im, (string) ($convo->platform ?? 'W'), $delaySeconds);
        } catch (\Throwable $e) {
            Log::warning('[AR-DISPATCH] send failed: ' . $e->getMessage(), [
                'rule_id'      => $rule->id,
                'workspace_id' => $workspaceId,
            ]);
            return $rule;
        }

        $this->recordFire($rule, $needleDigits, $body, $guard, $convo);
        return $rule;
    }

    /**
     * Dispatch the bubble via InboxDispatcher. When $delaySeconds > 0 the
     * actual transport call is deferred to app()->terminating() — by then
     * the provider's inbound webhook has already received its 200, so a
     * short sleep here paces the reply without risking a Meta/Twilio retry.
     * Capped by the caller to a worker-friendly ceiling.
     */
    private function shipInbox(InboxMessage $im, string $platform, int $delaySeconds): void
    {
        $send = function () use ($im, $platform) {
            try {
                $result = app(InboxDispatcher::class)->send($im, $platform);
                $im->status = ($result['ok'] ?? false) ? 'sent' : 'failed';
                if (!empty($result['provider_id'])) $im->wa_message_id = $result['provider_id'];
                $im->save();
            } catch (\Throwable $e) {
                Log::warning('[AR-DISPATCH] deferred send failed: ' . $e->getMessage(), ['im_id' => $im->id]);
                try { $im->status = 'failed'; $im->save(); } catch (\Throwable $e2) {}
            }
        };

        $delaySeconds = max(0, min(30, $delaySeconds));
        if ($delaySeconds > 0 && !app()->runningInConsole()) {
            app()->terminating(function () use ($send, $delaySeconds) {
                sleep($delaySeconds);
                $send();
            });
        } else {
            $send();
        }
    }

    /**
     * Build + ship a rule's reply for the auto-responder paths — a welcome
     * greeting, OR a keyword/welcome rule's OUTSIDE-HOURS message. Mirrors the
     * keyword custom/flow/media/template dispatch, but the content variant is
     * switchable: $useOutside pulls the rule's outside-hours composer
     * (outsideHoursContents) instead of its primary reply (selectedContents).
     * Records the fire so the resend + agent-override gates observe it.
     */
    private function shipRuleReply(KeywordReply $rule, Conversation $convo, string $needleDigits, ?Contact $contact, $guard, bool $useOutside = false): ?KeywordReply
    {
        $workspaceId = (int) ($convo->workspace_id ?? 0);
        $replyType   = strtolower((string) ($rule->reply_type ?? 'custom')) ?: 'custom';

        // FLOW reply — enrol into the selected flow (never for the outside-hours
        // message, which is always a plain content reply).
        if ($replyType === 'flow' && ! $useOutside) {
            $flow = $rule->flow_id ? \App\Models\Flow::find($rule->flow_id) : null;
            if ($flow) {
                $enrollContact = $contact ?: $this->findOrMakeContact($workspaceId, $convo, $needleDigits);
                if ($enrollContact) {
                    try {
                        app(\App\Services\Flow\FlowEnrollmentService::class)->enroll($enrollContact, $flow);
                    } catch (\Throwable $e) {
                        Log::warning('[AR-RESPONDER] flow enrol failed: ' . $e->getMessage(), ['rule_id' => $rule->id]);
                    }
                }
            }
            $this->recordFire($rule, $needleDigits, '', $guard, $convo);
            return $rule;
        }

        // Content variant: outside-hours vs primary.
        $content   = $useOutside ? $rule->outsideHoursContents->first() : $rule->selectedContents->first();
        $replyText = $content ? $this->substitute((string) ($content->content ?? ''), $contact, $needleDigits, $workspaceId) : '';
        $ctype     = strtolower((string) ($content?->content_type ?? $rule->message_type ?? 'text'));
        $delay     = max(0, min(30, (int) ($rule->timeout ?? 0)));

        $im = new InboxMessage();
        $im->conversation_id = $convo->id;
        $im->user_id         = $convo->user_id ?? null;
        $im->to_number       = $needleDigits;
        $im->direction       = 'out';
        $im->status          = 'pending';
        $meta = [
            'auto_reply_id' => $rule->id,
            'source'        => 'auto_reply',
            'welcome'       => ($rule->trigger_type ?? '') === 'welcome' ? 1 : null,
            'ar_kind'       => (string) ($rule->trigger_type ?? '') ?: null,
            'outside_hours' => $useOutside ? 1 : null,
        ];

        $shipped = false;
        if ($ctype === 'template' && $content && $content->template_id) {
            $tpl = \App\Models\WaTemplate::find((int) $content->template_id);
            if ($tpl && (int) ($tpl->workspace_id ?? $workspaceId) === $workspaceId) {
                $meta['template_id']       = $tpl->id;
                $meta['template_name']     = (string) ($tpl->name ?? $tpl->template_name ?? '');
                $meta['template_language'] = (string) ($tpl->language ?? $tpl->lang ?? 'en');
                $im->body = $replyText !== '' ? $replyText : (string) ($tpl->body ?? $tpl->template_body ?? '');
                $shipped  = true;
            }
        } elseif (in_array($ctype, ['image', 'video', 'document'], true) && $content && $content->file_path) {
            $im->media_path = $content->file_path;
            $im->media_type = $ctype;
            $im->body       = $replyText;
            $shipped        = true;
        } elseif ($replyText !== '') {
            $im->body = $replyText;
            $shipped  = true;
        }

        if (! $shipped) {
            return null;
        }

        try {
            $im->meta = array_filter($meta, fn ($v) => $v !== null && $v !== '');
            $im->save();
            $this->shipInbox($im, (string) ($convo->platform ?? 'W'), $delay);
        } catch (\Throwable $e) {
            Log::warning('[AR-RESPONDER] send failed: ' . $e->getMessage(), ['rule_id' => $rule->id]);
            return $rule;
        }
        $this->recordFire($rule, $needleDigits, '', $guard, $convo);
        return $rule;
    }

    /**
     * Write the per-fire log + bump counters + start the conversation
     * cooldown. Shared by every reply type so analytics + the AutoReplyGuard
     * window behave identically regardless of which branch fired.
     */
    private function recordFire(KeywordReply $rule, string $phone, string $body, $guard, Conversation $convo): void
    {
        try {
            KeywordReplyLog::create([
                'keyword_reply_id' => $rule->id,
                'contact_phone'    => $phone,
                'matched_text'     => $body,
                'fired_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[AR-DISPATCH] log insert failed: ' . $e->getMessage());
        }
        try {
            $rule->increment('trigger_count');
            $rule->forceFill(['last_triggered_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {}
        // Without markReplied the next inbound on the same conversation
        // re-fires immediately — the guard's last_auto_reply_at is only
        // written by callers, never by the guard itself.
        try {
            if ($guard && method_exists($guard, 'markReplied')) {
                $guard->markReplied($convo);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Flow enrolment needs a Contact subject. When the inbound phone has no
     * contact row yet (the decrypt-scan above found none), create a minimal
     * one so the flow can run. mobile is encrypted, so the scan already
     * proved no duplicate exists — a plain create is safe.
     */
    private function findOrMakeContact(int $workspaceId, Conversation $convo, string $digits): ?Contact
    {
        try {
            return Contact::create([
                'workspace_id' => $workspaceId,
                'user_id'      => $convo->user_id ?? null,
                'name'         => $convo->name ?: $digits,
                'mobile'       => $digits,
            ]);
        } catch (\Throwable $e) {
            Log::debug('[AR-DISPATCH] findOrMakeContact failed: ' . $e->getMessage());
            return null;
        }
    }

    private function resolveReplyText(KeywordReply $rule, ?Contact $contact, string $phone): string
    {
        // Reply payload lives on `keyword_reply_contents.content` (NOT
        // a `reply_text` column on the rule itself). WaDesk's schema
        // stores one or more selected content variants per rule via the
        // `selectedContents` relation; we ship the first selected text
        // variant's `content` field. Media replies (image/video/document)
        // would be handled at the dispatch-payload level but for the
        // text path we just substitute placeholders.
        $text = '';
        if (method_exists($rule, 'selectedContents')) {
            $first = $rule->selectedContents->first();
            if ($first) {
                // KeywordReplyContent stores body text in `content`.
                // `text`/`body` are fallbacks for any pre-migration rows.
                $text = (string) ($first->content
                    ?? $first->text
                    ?? $first->body
                    ?? '');
            }
        }
        return $this->substitute($text, $contact, $phone, (int) ($rule->workspace_id ?? 0));
    }

    /**
     * Placeholder substitution shared by the keyword reply and the auto-responder
     * (welcome / outside-hours) paths — workspace {{attributes}} first, then the
     * contact/phone tokens. Kept as one method so every path resolves identically.
     */
    private function substitute(string $text, ?Contact $contact, string $phone, int $workspaceId): string
    {
        if ($text === '') return '';

        // Workspace-attribute pass first ({{promo_key}}, {{order_id}}, …) —
        // mirrors AutoReplyController::resolveReplyText. No variable_map on
        // keyword replies, so named {{key}} resolution only.
        if (str_contains($text, '{{') && $workspaceId > 0) {
            $text = app(\App\Services\AttributeResolver::class)->resolve($text, [], $workspaceId);
        }

        $name      = $contact?->name ?: 'there';
        $firstName = $contact?->first_name ?: ($contact?->name ? explode(' ', $contact->name)[0] : 'there');
        $email     = $contact?->email ?: '';

        return strtr($text, [
            '{{name}}'       => $name,
            '{{first_name}}' => $firstName,
            '{{mobile}}'     => $phone,
            '{{phone}}'      => $phone,
            '{{email}}'      => $email,
        ]);
    }
}

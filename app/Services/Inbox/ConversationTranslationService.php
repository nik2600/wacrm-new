<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\Workspace;
use App\Services\PlanLimitGuard;
use App\Services\Translator;

/**
 * Real-time conversation translation — the single brain wired into every
 * surface (team inbox inbound + outbound, AI agent, chatbot widget,
 * auto-reply). It NEVER throws into a send/receive path: every public method
 * is best-effort and swallows its own errors.
 *
 * Column contract (see the 2026_06_20 migration):
 *   - `body` is always the canonical WhatsApp text (inbound = customer's
 *     original; outbound = what was actually delivered).
 *   - `translated_body` is the convenience translation into the VIEWING
 *     agent's language.
 *   - `detected_language` / `is_translated` drive the UI badge.
 *   - `conversations.customer_language` is pinned once from the first inbound
 *     (research: detect-once-and-pin beats re-detecting every short message).
 *
 * Gating: the plan feature `access_translation` AND the workspace's
 * `inbox_translate` toggle must both be on.
 */
class ConversationTranslationService
{
    /** Per-request memo so one webhook doesn't re-resolve the workspace N times. */
    private array $cfgCache = [];

    /**
     * Resolve [enabled, agentLang] for a workspace.
     * agentLang defaults to 'en' when the workspace hasn't set a language.
     *
     * @return array{enabled: bool, lang: string}
     */
    public function config(?int $wsId): array
    {
        $wsId = (int) ($wsId ?? 0);
        if ($wsId <= 0) return ['enabled' => false, 'lang' => 'en'];
        if (isset($this->cfgCache[$wsId])) return $this->cfgCache[$wsId];

        $cfg = ['enabled' => false, 'lang' => 'en'];
        try {
            $ws = Workspace::find($wsId);
            if ($ws) {
                $lang = strtolower(trim((string) ($ws->default_language ?: 'en'))) ?: 'en';
                $toggle = (bool) ($ws->inbox_translate ?? true);
                $cfg = [
                    'enabled' => $toggle && PlanLimitGuard::hasFeature($ws, 'access_translation'),
                    'lang'    => $lang,
                ];
            }
        } catch (\Throwable $e) {
            // leave disabled
        }
        return $this->cfgCache[$wsId] = $cfg;
    }

    /**
     * Inbound: detect the customer's language, pin it on the conversation, and
     * store the agent-language translation alongside the original. Called from
     * the InboxMessage `created` observer for direction='in' — so it covers
     * EVERY inbound site (WABA / Baileys / Twilio / widget) at once.
     */
    public function ingestInbound(InboxMessage $m): void
    {
        try {
            $body = trim((string) $m->body);
            if ($body === '' || $m->is_translated) return;

            $convo = $m->relationLoaded('conversation') ? $m->conversation : Conversation::find($m->conversation_id);
            if (!$convo) return;

            $cfg = $this->config($convo->workspace_id);
            if (!$cfg['enabled']) return;
            $agentLang = $cfg['lang'];

            $res  = Translator::detectAndTranslate($body, $agentLang);
            $lang = strtolower(trim((string) ($res['language'] ?? '')));

            // Pin the customer's language once (first confident inbound).
            if ($lang !== '' && empty($convo->customer_language)) {
                $convo->forceFill(['customer_language' => $lang])->saveQuietly();
            }

            if ($lang !== '' && $lang !== $agentLang && !empty($res['text'])) {
                $m->forceFill([
                    'detected_language' => $lang,
                    'translated_body'   => (string) $res['text'],
                    'is_translated'     => true,
                ])->saveQuietly();
            } elseif ($lang !== '') {
                $m->forceFill(['detected_language' => $lang])->saveQuietly();
            }
        } catch (\Throwable $e) {
            // never break inbound ingest
        }
    }

    /**
     * Outbound: translate an operator-typed reply into the customer's language
     * BEFORE it's dispatched. Mutates $m->body IN-MEMORY to the customer-language
     * text (so the wire + branding footer use it), and persists the agent's
     * original into `translated_body` for the thread view.
     *
     * No-op when: disabled, no pinned customer language, target == agent lang,
     * or the body is ALREADY in the customer's language (e.g. an AI reply that
     * was generated natively in that language) — so AI/keyword replies are never
     * double-translated or corrupted.
     */
    public function translateOutbound(InboxMessage $m): void
    {
        try {
            $typed = trim((string) $m->body);
            if ($typed === '' || $m->is_translated) return;

            // Never translate a forwarded relay (must stay verbatim) or a
            // template send — a template has a FIXED authored language (WABA
            // approved per-language, Instagram pre-authored) and must ship as
            // written, not be re-translated into the customer's pinned language.
            // The inbox composer stores the template identity in meta (NOT the
            // template_id column), so check BOTH: the column AND meta.template_*.
            $meta = is_array($m->meta) ? $m->meta : [];
            if (!empty($meta['forwarded'])
                || !empty($m->template_id)
                || !empty($meta['template_id'])
                || !empty($meta['template_name'])
                || ($meta['type'] ?? null) === 'template') return;

            $convo = $m->relationLoaded('conversation') ? $m->conversation : Conversation::find($m->conversation_id);
            if (!$convo) return;

            $target = strtolower(trim((string) ($convo->customer_language ?? '')));
            if ($target === '') return;

            $cfg = $this->config($convo->workspace_id);
            if (!$cfg['enabled'] || $target === $cfg['lang']) return;

            // One call detects the typed language AND translates to the target.
            $res = Translator::detectAndTranslate($typed, $target);
            $srcLang = strtolower(trim((string) ($res['language'] ?? '')));
            $out     = (string) ($res['text'] ?? '');

            // Already in the customer's language (AI/keyword reply) or failed → send as typed.
            if ($out === '' || $srcLang === $target) return;

            $m->forceFill([
                'translated_body'   => $typed,    // what the agent wrote (their language)
                'detected_language' => $target,   // delivered language
                'is_translated'     => true,
            ])->saveQuietly();

            // Send the translation (caller's branding footer appends to this).
            $m->body = $out;
        } catch (\Throwable $e) {
            // never break a send
        }
    }

    /**
     * Translate a free string for the web chatbot widget / AI context.
     * Returns ['language' => detected, 'text' => translated] (text falls back
     * to the original on any failure).
     *
     * @return array{language: ?string, text: string}
     */
    public function translateFor(string $text, string $targetLang): array
    {
        $text = trim($text);
        if ($text === '') return ['language' => null, 'text' => $text];
        try {
            $res = Translator::detectAndTranslate($text, strtolower($targetLang));
            return [
                'language' => $res['language'] ?? null,
                'text'     => (string) ($res['text'] ?? $text) ?: $text,
            ];
        } catch (\Throwable $e) {
            return ['language' => null, 'text' => $text];
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ChatbotWidget;
use App\Models\ChatbotWidgetVisitor;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\Message;
use App\Services\AiChat\AiChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Public-facing chatbot widget endpoints. No auth — `embed_token`
 * acts as the bearer. The token is unguessable (48 chars) and
 * rotatable; rotating it kills any leaked snippets without
 * affecting stored conversations.
 *
 * Routes:
 *   GET  /widget/{token}/embed.js       — pasteable snippet payload
 *   GET  /widget/{token}/chat           — iframe contents
 *   GET  /widget/{token}/api/config     — JSON config for the iframe JS
 *   POST /widget/{token}/api/message    — visitor message → AI reply
 *
 * Conversations persist into the same `conversations` table WhatsApp
 * threads use; the individual bubbles go into `inbox_messages` (the table
 * the team-inbox thread view reads) so the team inbox renders widget chats
 * automatically — no separate inbox surface needed.
 */
class ChatbotWidgetPublicController extends Controller
{
    public function __construct(private AiChatService $ai)
    {
        // Writes its own InboxMessage rows; keep the Message model's
        // inbox mirror off so sends don't appear twice in the thread.
        \App\Models\Message::$skipInboxMirror = true;
    }

    /**
     * The embed.js snippet — small bootstrapper the customer pastes
     * onto their site. It injects a launcher button + (on click) an
     * iframe to /widget/{token}/chat. Returned as text/javascript so
     * `<script src="...">` works directly.
     */
    public function embedJs(string $token): Response
    {
        $widget = ChatbotWidget::where('embed_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$widget) {
            return response("// chatbot widget not found or paused\n", 404)
                ->header('Content-Type', 'application/javascript');
        }

        $chatUrl   = url('/widget/' . $token . '/chat');
        $position  = $widget->position === 'bottom_left' ? 'left:24px;' : 'right:24px;';
        $btnColor  = htmlspecialchars($widget->button_color ?: '#075E54');
        $btnImg    = htmlspecialchars((string) $widget->button_image_url);
        $autoOpen  = $widget->auto_open ? 'true' : 'false';

        // Inline SVG fallback when no custom image is configured. Per
        // [[feedback_no_emojis]] never use emoji characters in JS that
        // ships to operators; keep this strictly SVG.
        $svgFallback = "<svg viewBox='0 0 24 24' width='28' height='28' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z'/></svg>";

        $js = <<<JS
(function() {
  if (window.__wadeskWidget_{$widget->id}) return;
  window.__wadeskWidget_{$widget->id} = true;

  var d = document;
  var wrap = d.createElement('div');
  wrap.style.cssText = 'position:fixed;bottom:24px;{$position}z-index:2147483600;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif';
  wrap.innerHTML =
    "<button id='wsnap-launch-{$widget->id}' aria-label='Open chat' style=\"width:56px;height:56px;border-radius:9999px;border:0;background:{$btnColor};box-shadow:0 6px 24px rgba(0,0,0,.18);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;\">" +
    ("{$btnImg}" ? "<img src='{$btnImg}' style='width:36px;height:36px;border-radius:50%' alt=''>" : "{$svgFallback}") +
    "</button>" +
    "<div id='wsnap-frame-{$widget->id}' style='display:none;position:fixed;bottom:96px;{$position}width:380px;height:560px;max-width:calc(100vw - 32px);max-height:calc(100vh - 120px);border-radius:14px;overflow:hidden;box-shadow:0 12px 48px rgba(0,0,0,.25);background:#fff'><iframe src='{$chatUrl}' style='width:100%;height:100%;border:0;display:block'></iframe></div>";
  d.body.appendChild(wrap);

  var btn = d.getElementById('wsnap-launch-{$widget->id}');
  var frame = d.getElementById('wsnap-frame-{$widget->id}');
  function toggle() { frame.style.display = (frame.style.display === 'none' ? 'block' : 'none'); }
  btn.addEventListener('click', toggle);
  if ({$autoOpen}) { setTimeout(toggle, 1200); }
})();
JS;

        return response($js, 200)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    /**
     * Iframe HTML — the actual chat surface. Renders the bubble
     * stack, input box, and a tiny client script that talks to
     * /api/config + /api/message.
     */
    public function chat(string $token): View|Response
    {
        $widget = ChatbotWidget::where('embed_token', $token)
            ->where('status', 'active')
            ->first();
        if (!$widget) return response('Widget not found', 404);
        return view('public.widget.chat', compact('widget'));
    }

    /**
     * Initial config for the iframe JS. The first call also mints a
     * `visitor_uuid` cookie so a returning visitor stitches back to
     * the same row + conversation.
     */
    public function config(string $token, Request $request): JsonResponse
    {
        $widget = ChatbotWidget::where('embed_token', $token)
            ->where('status', 'active')
            ->first();
        if (!$widget) return response()->json(['ok' => false, 'error' => 'widget_not_found'], 404);

        [$visitor, $isNew] = $this->resolveVisitor($widget, $request);

        $waUrl = null;
        if ($widget->usesWhatsApp()) {
            $phone = preg_replace('/\D+/', '', (string) ($widget->target_whatsapp_cc . $widget->target_whatsapp_number));
            if ($phone) {
                $msg = urlencode((string) $widget->prefilled_message);
                $waUrl = "https://wa.me/{$phone}" . ($msg !== '' ? "?text={$msg}" : '');
            }
        }

        $payload = [
            'ok' => true,
            'widget' => [
                'name'              => $widget->name,
                'mode'              => $widget->mode,
                'header_title'      => $widget->header_title,
                'header_bg'         => $widget->header_bg,
                'header_text'       => $widget->header_text_color,
                'welcome_message'   => $widget->welcome_message,
                'bubble_color'      => $widget->message_bubble_color,
                'bubble_text'       => $widget->message_text_color,
                'body_bg_kind'      => $widget->body_bg_kind,
                'body_bg_color'     => $widget->body_bg_color,
                'body_bg_image_url' => $widget->body_bg_image_url,
                'button_label'      => $widget->button_label,
                'button_bg'         => $widget->action_button_bg,
                'button_text'       => $widget->action_button_text_color,
                'collect_name'      => $widget->collect_name,
                'collect_email'     => $widget->collect_email,
                'collect_phone'     => $widget->collect_phone,
                'whatsapp_url'      => $waUrl,
            ],
            'visitor' => [
                'uuid'  => $visitor->visitor_uuid,
                'name'  => $visitor->name,
                'email' => $visitor->email,
                'phone' => $visitor->phone,
            ],
            // Past messages for THIS visitor's conversation, so the widget can
            // repaint the chat on reload instead of starting fresh. Empty for a
            // brand-new visitor (the widget then shows the welcome message).
            'transcript' => $this->recentTranscript($visitor),
        ];

        $resp = response()->json($payload);
        if ($isNew) {
            // Long-lived cookie so a return visit stitches the same
            // conversation (visible to agents as one continuous thread).
            $resp->cookie('wsnap_vid_' . $widget->id, $visitor->visitor_uuid, 60 * 24 * 365);
        }
        return $resp;
    }

    /**
     * POST a visitor message. Behavior:
     *   1. Resolve/upsert visitor by uuid.
     *   2. Update name/email/phone if supplied (first contact form).
     *   3. Ensure a `conversations` row exists with channel='chatbot_widget'.
     *   4. Persist visitor message as a `messages` row (direction=in).
     *   5. If widget has an assistant, generate AI reply + persist
     *      as direction=out, and return it.
     */
    public function message(string $token, Request $request): JsonResponse
    {
        Log::info('[WIDGET-INBOX] message(): received', ['token' => substr($token, 0, 12) . '…']);
        $widget = ChatbotWidget::where('embed_token', $token)
            ->where('status', 'active')
            ->first();
        if (!$widget) {
            Log::warning('[WIDGET-INBOX] message(): widget NOT found or not active — chat cannot be saved', ['token' => substr($token, 0, 12) . '…']);
            return response()->json(['ok' => false, 'error' => 'widget_not_found'], 404);
        }

        $data = $request->validate([
            'body'  => 'required|string|max:8000',
            'name'  => 'nullable|string|max:120',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|string|max:32',
        ]);

        [$visitor, ] = $this->resolveVisitor($widget, $request);

        // Stamp any contact-form fields the visitor just submitted.
        $dirty = false;
        foreach (['name', 'email', 'phone'] as $f) {
            if (!empty($data[$f]) && $visitor->{$f} !== $data[$f]) {
                $visitor->{$f} = $data[$f]; $dirty = true;
            }
        }
        if ($dirty) $visitor->save();

        $convo = $this->ensureConversation($widget, $visitor);

        // Persist the inbound message. MUST be an InboxMessage (not Message)
        // so the team-inbox thread view — which reads $conv->inboxMessages()
        // (the inbox_messages table) — actually shows the widget chat. Writing
        // to `messages` left widget threads opening blank (preview in the list
        // but total_on_conv=0 in the thread).
        $inbound = InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => $widget->user_id,
            'direction'       => 'in',
            'from_number'     => $visitor->visitor_uuid,
            'to_number'       => 'widget-' . $widget->id,
            'body'            => $data['body'],
            'status'          => 'delivered',
            'meta'            => ['widget_id' => $widget->id, 'visitor_uuid' => $visitor->visitor_uuid, 'channel' => 'chatbot_widget'],
            'sent_at'         => now(),
        ]);
        $convo->update([
            'preview'         => mb_substr($data['body'], 0, 191),
            'last_message_at' => now(),
            'last_inbound_at' => now(),
            'unread_count'    => ($convo->unread_count ?? 0) + 1,
        ]);

        // These are exactly the columns the Team Inbox list query filters on
        // (workspace_id + provider(engine) + device_id + inbox_status). If the
        // chat still doesn't appear, compare these against the inbox diagnostic.
        Log::info('[WIDGET-INBOX] message(): inbound SAVED to inbox_messages', [
            'widget_id'        => $widget->id,
            'workspace_id'     => $widget->workspace_id,
            'conversation_id'  => $convo->id,
            'inbox_message_id' => $inbound->id,
            'provider'         => $convo->provider,
            'channel'          => $convo->channel,
            'inbox_status'     => $convo->inbox_status,
            'device_id'        => $convo->device_id,
            'visitor_uuid'     => $visitor->visitor_uuid,
        ]);

        // AI reply, if the widget runs an assistant.
        $assistantReply = null;
        if ($widget->usesAi() && $widget->assistant) {
            try {
                $assistantReply = $this->ai->reply($widget->assistant, $convo, $data['body']);
            } catch (\Throwable $e) {
                Log::error('[WIDGET] AI reply threw: ' . $e->getMessage(), [
                    'widget_id'    => $widget->id,
                    'assistant_id' => $widget->assistant_id,
                ]);
                $assistantReply = (string) ($widget->assistant->fallback_message
                    ?: "Sorry, I'm having trouble right now. A team member will follow up shortly.");
            }
        }

        $outbound = null;
        if ($assistantReply) {
            $outbound = InboxMessage::create([
                'conversation_id' => $convo->id,
                'user_id'         => $widget->user_id,
                'direction'       => 'out',
                'from_number'     => 'widget-' . $widget->id,
                'to_number'       => $visitor->visitor_uuid,
                'body'            => $assistantReply,
                'status'          => 'sent',
                'meta'            => [
                    'widget_id'    => $widget->id,
                    'assistant_id' => $widget->assistant_id,
                    'source'       => 'ai_chat_assistant',
                    'channel'      => 'chatbot_widget',
                ],
                'sent_at'         => now(),
            ]);
            $convo->update([
                'preview'          => mb_substr($assistantReply, 0, 191),
                'last_message_at'  => now(),
                'last_outbound_at' => now(),
            ]);
            Log::info('[WIDGET-INBOX] message(): AI reply SAVED to inbox_messages', [
                'conversation_id'  => $convo->id,
                'inbox_message_id' => $outbound->id,
            ]);
        }

        return response()->json([
            'ok'       => true,
            'inbound'  => ['id' => $inbound->id],
            'reply'    => $outbound ? ['id' => $outbound->id, 'body' => $outbound->body, 'at' => $outbound->sent_at] : null,
        ]);
    }

    /**
     * GET /widget/{token}/api/history?since=ID
     *   For polling — returns any outbound messages since the given
     *   id, so when a human agent replies from the team inbox the
     *   visitor's widget picks it up without a refresh.
     */
    public function history(string $token, Request $request): JsonResponse
    {
        $widget = ChatbotWidget::where('embed_token', $token)->first();
        if (!$widget) return response()->json(['ok' => false, 'error' => 'widget_not_found'], 404);

        [$visitor, ] = $this->resolveVisitor($widget, $request);
        if (!$visitor->conversation_id) return response()->json(['ok' => true, 'messages' => []]);

        // Read from inbox_messages (same table the AI reply + any team-inbox
        // agent reply land in), so the visitor's widget polls up BOTH the
        // assistant's replies AND a human agent's replies from the team inbox.
        $since = (int) $request->input('since', 0);
        $msgs = InboxMessage::query()
            ->where('conversation_id', $visitor->conversation_id)
            ->where('direction', 'out')
            ->where('id', '>', $since)
            ->orderBy('id')
            ->get(['id', 'body', 'sent_at', 'agent_id']);

        return response()->json([
            'ok' => true,
            'messages' => $msgs->map(fn ($m) => [
                'id'   => $m->id,
                'body' => $m->body,
                'at'   => $m->sent_at,
                'from' => $m->agent_id ? 'agent' : 'assistant',
            ]),
        ]);
    }

    /* ----------------------------- helpers ----------------------------- */

    /**
     * Recent messages of the visitor's conversation, oldest→newest, so the
     * widget repaints the whole chat on reload. Direction is flipped to the
     * WIDGET's point of view: the DB stores the visitor's message as 'in'
     * (inbound to the business) but in the widget it's the visitor's own
     * bubble on the RIGHT ('out'); a business reply is DB 'out' → widget 'in'.
     */
    private function recentTranscript(ChatbotWidgetVisitor $visitor): array
    {
        if (!$visitor->conversation_id) return [];

        return InboxMessage::query()
            ->where('conversation_id', $visitor->conversation_id)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'direction', 'body', 'agent_id'])
            ->map(fn ($m) => [
                'id'   => $m->id,
                'dir'  => $m->direction === 'in' ? 'out' : 'in',   // widget orientation
                'body' => $m->body,
                'from' => $m->direction === 'in' ? 'visitor' : ($m->agent_id ? 'agent' : 'assistant'),
            ])
            ->all();
    }


    /**
     * Resolve the visitor by cookie (or X-Visitor-UUID header from
     * the iframe JS). Returns [visitor, isNewlyCreated].
     */
    private function resolveVisitor(ChatbotWidget $widget, Request $request): array
    {
        $cookieKey = 'wsnap_vid_' . $widget->id;
        $uuid = (string) ($request->cookie($cookieKey)
            ?: $request->header('X-Visitor-UUID')
            ?: $request->input('visitor_uuid')
            ?: '');

        // Critical: scope by widget_id as well so a UUID cookie set by
        // Widget A in Workspace 1 can't be replayed against Widget B in
        // Workspace 2 to grab the foreign workspace's conversation.
        $visitor = $uuid
            ? ChatbotWidgetVisitor::where('visitor_uuid', $uuid)
                ->where('widget_id', $widget->id)->first()
            : null;
        $isNew = false;
        if (!$visitor) {
            $visitor = new ChatbotWidgetVisitor();
            $visitor->workspace_id = $widget->workspace_id;
            $visitor->widget_id    = $widget->id;
            $visitor->visitor_uuid = ChatbotWidgetVisitor::freshUuid();
            $visitor->first_seen_at = now();
            $visitor->referrer_url = mb_substr((string) $request->header('referer'), 0, 1024);
            $visitor->user_agent   = mb_substr((string) $request->userAgent(), 0, 512);
            $visitor->ip           = (string) $request->ip();
            $visitor->save();
            $isNew = true;
        }
        $visitor->last_seen_at = now();
        $visitor->save();
        return [$visitor, $isNew];
    }

    /**
     * Ensure there's a `conversations` row for this visitor under
     * `channel='chatbot_widget'`. Reused across the visitor's
     * lifetime so the team inbox shows one continuous thread.
     */
    private function ensureConversation(ChatbotWidget $widget, ChatbotWidgetVisitor $visitor): Conversation
    {
        if ($visitor->conversation_id) {
            $existing = Conversation::find($visitor->conversation_id);
            if ($existing) {
                Log::info('[WIDGET-INBOX] ensureConversation(): REUSE existing', [
                    'conversation_id' => $existing->id,
                    'provider'        => $existing->provider,
                    'channel'         => $existing->channel,
                    'inbox_status'    => $existing->inbox_status,
                    'device_id'       => $existing->device_id,
                    'workspace_id'    => $existing->workspace_id,
                ]);
                return $existing;
            }
        }
        $convo = Conversation::create([
            'user_id'      => $widget->user_id,
            'workspace_id' => $widget->workspace_id,
            'raw_jid'      => 'widget-' . $widget->id . '-' . $visitor->visitor_uuid,
            'title'        => $visitor->displayName(),
            'preview'      => $widget->welcome_message ? mb_substr($widget->welcome_message, 0, 191) : '',
            'status'       => 'open',
            'platform'     => 'WIDGET',
            // Widget conversations belong to whichever engine the
            // workspace is on (their outbound replies route through it).
            'provider'     => \App\Services\WorkspaceEngine::for($widget->workspace_id),
            'origin'       => 'web',
            'channel'      => 'chatbot_widget',
            'inbox_status' => 'open',
            'last_message_at' => now(),
            'routing_meta' => [
                'widget_id'    => $widget->id,
                'widget_name'  => $widget->name,
                'visitor_uuid' => $visitor->visitor_uuid,
                'visitor_name' => $visitor->name,
                'assistant_id' => $widget->assistant_id,
            ],
        ]);
        $visitor->conversation_id = $convo->id;
        $visitor->save();

        // The 4 fields the Team Inbox list query filters on. If a chat is saved
        // but never appears, cross-check these against the inbox diagnostic log:
        // provider must be in the workspace's engines, device_id null is allowed.
        Log::info('[WIDGET-INBOX] ensureConversation(): CREATED new conversation', [
            'conversation_id' => $convo->id,
            'workspace_id'    => $convo->workspace_id,
            'provider'        => $convo->provider,       // must be in enginesFor(workspace)
            'channel'         => $convo->channel,        // chatbot_widget
            'inbox_status'    => $convo->inbox_status,   // open
            'device_id'       => $convo->device_id,      // null (allowed by deviceAlive)
        ]);

        // Seed the welcome message so the visitor sees it as the
        // first bubble in the team-inbox thread too.
        if (!empty($widget->welcome_message)) {
            Message::create([
                'conversation_id' => $convo->id,
                'user_id'         => $widget->user_id,
                'direction'       => 'out',
                'from_number'     => 'widget-' . $widget->id,
                'to_number'       => $visitor->visitor_uuid,
                'body'            => $widget->welcome_message,
                'status'          => 'sent',
                'meta'            => ['widget_id' => $widget->id, 'source' => 'welcome'],
                'sent_at'         => now(),
            ]);
        }
        return $convo;
    }
}

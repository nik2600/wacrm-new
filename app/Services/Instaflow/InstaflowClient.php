<?php

namespace App\Services\Instaflow;

use App\Models\SystemSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The WaDesk → Instaflow bridge.
 *
 * Instaflow runs as its OWN deployment and owns the real Instagram engine
 * (Meta OAuth, webhooks, Graph API). WaDesk never talks to Meta for IG — it
 * talks to Instaflow, and Instaflow's data surfaces in WaDesk's unified inbox.
 *
 * The two prove they share a secret on every call via the X-Instaflow-Secret
 * header — the SAME secret the admin pastes on the Add-ons "Connect Instaflow"
 * card (stored encrypted in SystemSetting). One direction lives here (WaDesk
 * calling Instaflow); the reverse (Instaflow pushing new IG messages to WaDesk)
 * lands on POST /api/instaflow/inbound, guarded by the same secret.
 *
 * Contract (Instaflow must expose, all under {url}, secret in the header):
 *   GET  /api/wadesk/handshake                 → {ok:true, service:"instaflow"}
 *   GET  /api/wadesk/conversations?account=..  → [{id,account,name,handle,avatar,last_at,unread}]
 *   GET  /api/wadesk/conversations/{id}/messages → [{id,from_me,type,text,media_url,at}]
 *   POST /api/wadesk/conversations/{id}/reply  → {ok:true, message_id}   body:{type,text,media_url}
 *   GET  /api/wadesk/accounts                  → [{id,username,name,avatar,connected}]
 */
class InstaflowClient
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $secret  = '',
    ) {}

    /** Build from the admin-saved connection (URL + shared secret). */
    public static function fromSettings(): self
    {
        return new self(
            rtrim((string) SystemSetting::get('instaflow_url', ''), '/'),
            (string) SystemSetting::get('instaflow_secret', ''),
        );
    }

    /** Is a connection even configured? (URL + secret both present.) */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->secret !== '';
    }

    /** Public base URL of the connected Instaflow deployment (for deep-links
     *  into its flow builder). Empty when not configured. */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Was the last saved handshake successful? Cheap — reads the stored flag. */
    public function isConnected(): bool
    {
        return $this->isConfigured() && (bool) SystemSetting::get('instaflow_connected', false);
    }

    /** Live handshake — proves reachability + secret match right now. */
    public function handshake(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        try {
            $r = $this->request()->get('/api/wadesk/handshake');
            return $r->successful() && ($r->json('ok') === true || $r->json('service') === 'instaflow');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<int, array> IG conversations for the unified inbox. */
    public function conversations(?string $account = null): array
    {
        return $this->safeArray(
            fn () => $this->request()->get('/api/wadesk/conversations', array_filter(['account' => $account]))
        );
    }

    /** @return array<int, array> Messages in one IG conversation. */
    public function messages(string $conversationId): array
    {
        return $this->safeArray(
            fn () => $this->request()->get("/api/wadesk/conversations/{$conversationId}/messages")
        );
    }

    /**
     * @return array<int, array> Connected IG accounts on the Instaflow side.
     *
     * Pass $workspace to restrict to accounts stamped with that WaDesk workspace
     * (multi-tenant scoping). The no-arg call is unchanged — Instaflow returns
     * every account when ?workspace= is absent, which is what the "link existing"
     * picker wants (list all, let the admin choose).
     */
    public function accounts(?int $workspace = null, ?string $ownerEmail = null): array
    {
        // Scope the account list to a single owner. $ownerEmail is the WaDesk
        // user's email — Instaflow returns ONLY the IG accounts owned by the
        // Instaflow user with that same email, so the "link existing" picker
        // never leaks other tenants' accounts. When BOTH are null Instaflow
        // returns nothing (a bare call must not enumerate every account).
        $params = [];
        if ($workspace !== null)      $params['workspace']    = $workspace;
        if (($ownerEmail ?? '') !== '') $params['owner_email'] = $ownerEmail;
        return $this->safeArray(fn () => $this->request()->get('/api/wadesk/accounts', $params));
    }

    /**
     * Ask Instaflow to mint a signed one-time ticket for the "connect a new IG
     * account" popup. Returns the {ok, url} envelope; `url` is the Instaflow
     * OAuth entrypoint the WaDesk client opens in a popup. $returnUrl is where
     * Instaflow's "connected" page bounces the TOP window when there is no opener.
     */
    public function connectStart(int $workspace, string $returnUrl): array
    {
        return $this->post('/api/wadesk/connect/start', [
            'workspace_id' => $workspace,
            'return_url'   => $returnUrl,
        ]);
    }

    /**
     * Reply to an IG conversation. Instaflow performs the actual Graph send and
     * returns the provider message id. Returns ['ok'=>bool,'message_id'=>?string,'error'=>?string].
     *
     * Polymorphic on $type: text | image | video | audio | file | quick_replies | buttons.
     * $quickReplies / $buttons are only sent when non-empty (backward-compatible with
     * every existing 4-arg caller). For audio/file pass the explicit $type — the bridge
     * treats a media_url with no type as an image.
     */
    public function reply(
        string $conversationId,
        string $type,
        ?string $text = null,
        ?string $mediaUrl = null,
        array $quickReplies = [],
        array $buttons = []
    ): array {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Instaflow is not connected.'];
        }
        try {
            $body = array_filter([
                'type'      => $type,
                'text'      => $text,
                'media_url' => $mediaUrl,
            ], fn ($v) => $v !== null);
            if (! empty($quickReplies)) $body['quick_replies'] = array_values($quickReplies);
            if (! empty($buttons))      $body['buttons']       = array_values($buttons);

            $r = $this->request()->post("/api/wadesk/conversations/{$conversationId}/reply", $body);
            if (! $r->successful()) {
                return ['ok' => false, 'error' => 'Instaflow returned HTTP ' . $r->status()];
            }
            return ['ok' => true, 'message_id' => $r->json('message_id')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** React to a provider message. $messageId is the IG mid (InboxMessage.meta.instagram.message_id), not the local row id. */
    public function react(string $conversationId, string $messageId, string $reaction = '❤️'): array
    {
        return $this->post("/api/wadesk/conversations/{$conversationId}/react", ['message_id' => $messageId, 'reaction' => $reaction]);
    }

    /** Sender action: typing_on | typing_off | mark_seen. */
    public function action(string $conversationId, string $action): array
    {
        return $this->post("/api/wadesk/conversations/{$conversationId}/action", ['action' => $action]);
    }

    public function typingOn(string $conversationId): array  { return $this->action($conversationId, 'typing_on'); }
    public function typingOff(string $conversationId): array { return $this->action($conversationId, 'typing_off'); }
    public function markSeen(string $conversationId): array  { return $this->action($conversationId, 'mark_seen'); }

    /** @return array<int, array{id:int,name:string}> Active IG flows Instaflow can run. Unwraps the {ok,flows} envelope. */
    public function flows(?string $account = null): array
    {
        if (! $this->isConfigured()) {
            return [];
        }
        try {
            $r = $this->request()->get('/api/wadesk/flows', array_filter(['account' => $account]));
            $j = $r->successful() ? $r->json() : null;
            return is_array($j['flows'] ?? null) ? $j['flows'] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Fetch ONE Instaflow flow WITH its full node data so WaDesk can import +
     * edit it. Pass $adopt (a WaDesk flow id) to LINK this Instaflow flow to it,
     * so a later WaDesk save updates the same flow rather than duplicating.
     * Returns ['id','name','flow_data'=>array,'trigger_kind','trigger_keywords',
     * 'is_published'] or null on failure.
     */
    public function flow(int $flowId, ?int $adopt = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }
        try {
            $r = $this->request()->get('/api/wadesk/flows/' . $flowId, array_filter([
                'adopt' => $adopt ? (int) $adopt : null,
            ]));
            if (! $r->successful()) return null;
            $j = $r->json();
            return is_array($j['flow'] ?? null) ? $j['flow'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * List the account's Instagram templates (reusable DM snippets) so WaDesk can
     * import them. Returns [{id,name,type,body,items[]}] or [] on failure.
     */
    public function templates(?string $account = null): array
    {
        if (! $this->isConfigured()) {
            return [];
        }
        try {
            $r = $this->request()->get('/api/wadesk/templates', array_filter(['account' => $account]));
            $j = $r->successful() ? $r->json() : null;
            return is_array($j['templates'] ?? null) ? $j['templates'] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Trigger an Instaflow flow for this conversation. */
    public function runFlow(string $conversationId, int $flowId, ?string $text = null): array
    {
        return $this->post("/api/wadesk/conversations/{$conversationId}/run-flow", array_filter([
            'flow_id' => $flowId,
            'text'    => $text,
        ], fn ($v) => $v !== null));
    }

    /**
     * Push a WaDesk-authored Instagram flow to Instaflow so its native runtime
     * runs it. The two builders share the {flowNodes,flowEdges,vars} format, so
     * $flowData is sent verbatim (no translation). Keyed by $wadeskFlowId → a
     * re-save updates the same Instaflow flow. Setting the trigger fields makes
     * Instaflow wire the keyword automation (auto-fires on a matching DM).
     */
    public function pushFlow(string $account, int $wadeskFlowId, string $name, array $flowData, ?string $triggerKind = null, ?string $triggerKeywords = null, bool $published = false): array
    {
        return $this->post('/api/wadesk/flows', array_filter([
            'account'          => $account,
            'wadesk_flow_id'   => $wadeskFlowId,
            'name'             => $name,
            'flow_data'        => $flowData,
            'trigger_kind'     => $triggerKind,
            'trigger_keywords' => $triggerKeywords,
            'is_published'     => $published,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Push a WaDesk-authored Instagram template UP to Instaflow so it appears
     * in Instaflow's own template list (the reverse of templates(), which pulls
     * DOWN). Keyed by (account's workspace, name) on the Instaflow side, so a
     * re-save updates the same row instead of duplicating. $type is one of
     * text|quick_replies|buttons; $items is Instaflow's native shape:
     *   quick_replies → [{title, payload}]
     *   buttons       → [{type: postback|web_url, title, value}]
     */
    public function pushTemplate(string $account, string $name, string $type, string $body, array $items = []): array
    {
        return $this->post('/api/wadesk/templates', [
            'account' => $account,
            'name'    => $name,
            'type'    => $type ?: 'text',
            'body'    => $body,
            'items'   => $items,
        ]);
    }

    /** Shared POST → ['ok'=>bool, ...] envelope; never throws into the caller. */
    private function post(string $path, array $body): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'Instaflow is not connected.'];
        }
        try {
            $r = $this->request()->post($path, $body);
            if (! $r->successful()) {
                return ['ok' => false, 'error' => 'Instaflow returned HTTP ' . $r->status()];
            }
            $j = $r->json();
            return is_array($j) ? $j : ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Shared pending HTTP client — secret header + JSON + sane timeout on every call. */
    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Instaflow-Secret' => $this->secret])
            ->acceptJson()
            ->timeout(15);
    }

    /** Run a GET that returns a list; never throw into the caller — degrade to []. */
    private function safeArray(callable $call): array
    {
        if (! $this->isConfigured()) {
            return [];
        }
        try {
            /** @var Response $r */
            $r = $call();
            $data = $r->successful() ? $r->json() : null;
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

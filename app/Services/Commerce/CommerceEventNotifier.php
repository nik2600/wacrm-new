<?php

namespace App\Services\Commerce;

use App\Enums\WaProvider;
use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use App\Models\WaTemplate;
use App\Services\Waba\TemplateSender;
use App\Services\WhatsAppDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Sends ONE template to ONE recipient in response to a commerce event
 * (Shopify / WooCommerce order created, paid, fulfilled, …), routed to
 * whichever engine the workspace runs — Unofficial API (Baileys), the
 * official WhatsApp Business / Cloud API (WABA), or Twilio.
 *
 * This is the piece that was missing: the integration webhooks logged
 * events but never actually messaged anyone. Callers (ShopifyController,
 * WoocommerceController) build a flat $ctx map from the order payload and
 * hand it here together with the configured WaTemplate.
 *
 * Per-engine behaviour:
 *   WABA    → App\Services\Waba\TemplateSender (approval + quality + cap
 *             gates, positional {{1}} body params from $ctx['_positional']).
 *   Baileys → render the template body with $ctx (named {{token}}
 *             substitution) and send as text + the template's buttons.
 *   Twilio  → if the template has a registered ContentSid, send via the
 *             ContentSid path with positional ContentVariables; otherwise
 *             fall back to the rendered plain body.
 *
 * Returns: ['ok'=>bool, 'engine'=>string, 'provider_id'=>?string, 'error'=>?string]
 */
class CommerceEventNotifier
{
    public function __construct(private readonly WhatsAppDispatcher $dispatcher) {}

    public function notify(int $workspaceId, ?int $userId, string $toNumber, WaTemplate $tpl, array $ctx = [], ?string $senderKey = null): array
    {
        $to = preg_replace('/\D+/', '', (string) $toNumber);
        if ($to === '') {
            return ['ok' => false, 'engine' => 'none', 'provider_id' => null, 'error' => 'No recipient phone number on the order.'];
        }

        $engine     = $this->resolveEngine($workspaceId);
        $rendered    = $this->renderBody((string) ($tpl->template_body ?? ''), $ctx);
        $positional = $this->positionalVars((string) ($tpl->template_body ?? ''), $ctx);

        // Explicit "Send from" override (engine:id). Baileys id → a device (route
        // by its phone via from_number); WABA/Twilio id → a specific provider
        // config. Validated against this workspace + connected. Blank = Auto.
        $fromNumber = null;
        $configId   = null;
        if ($senderKey && str_contains($senderKey, ':')) {
            [$sEng, $sId] = explode(':', $senderKey, 2);
            $picked = WaProvider::tryFrom($sEng);
            if ($picked) {
                $engine = $picked;
                if ($sEng === 'baileys') {
                    $dev = \App\Models\Device::where('id', (int) $sId)->where('workspace_id', $workspaceId)->first();
                    if (!$dev) $dev = \App\Models\Device::where('id', (int) $sId)->whereNull('workspace_id')->where('user_id', $userId)->first();
                    if ($dev) $fromNumber = preg_replace('/\D+/', '', (string) ($dev->country_code . $dev->phone_number));
                } else {
                    $cfg = WaProviderConfig::where('id', (int) $sId)->where('workspace_id', $workspaceId)->where('provider', $sEng)->first();
                    if ($cfg) $configId = (int) $cfg->id;
                }
            }
        }

        try {
            return match ($engine) {
                WaProvider::Waba   => $this->sendWaba($tpl, $to, $positional, $workspaceId, $configId),
                WaProvider::Twilio => $this->sendTwilio($tpl, $to, $userId, $workspaceId, $rendered, $positional),
                default            => $this->sendBaileys($tpl, $to, $userId, $workspaceId, $rendered, $fromNumber),
            };
        } catch (\Throwable $e) {
            Log::warning('[CommerceNotifier] send threw', ['engine' => $engine->value, 'tpl' => $tpl->id, 'err' => $e->getMessage()]);
            return ['ok' => false, 'engine' => $engine->value, 'provider_id' => null, 'error' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------
    // Per-engine send
    // -----------------------------------------------------------------

    private function sendWaba(WaTemplate $tpl, string $to, array $positional, int $workspaceId, ?int $configId = null): array
    {
        // Explicit WABA account chosen in the "Send from" picker wins; else the
        // workspace's primary connected WABA number.
        $cfg = $configId
            ? WaProviderConfig::where('id', $configId)->where('workspace_id', $workspaceId)->where('provider', 'waba')->connected()->first()
            : null;
        $cfg = $cfg ?: WaProviderConfig::primaryForWorkspace($workspaceId)->where('provider', 'waba')->connected()->first();
        $res = app(TemplateSender::class)->send($tpl, $to, $positional, $cfg);
        return [
            'ok'          => (bool) ($res['ok'] ?? false),
            'engine'      => 'waba',
            'provider_id' => $res['wamid'] ?? null,
            'error'       => $res['ok'] ? null : ($res['error'] ?? 'WABA send failed'),
        ];
    }

    private function sendBaileys(WaTemplate $tpl, string $to, ?int $userId, int $workspaceId, string $rendered, ?string $fromNumber = null): array
    {
        $meta = [];
        $buttons = $tpl->buttons ?? [];
        if (is_array($buttons) && $buttons) {
            $meta['buttons'] = $buttons;
        }
        if (!empty($tpl->footer)) $meta['footer'] = (string) $tpl->footer;
        if (!empty($tpl->header)) $meta['header'] = (string) $tpl->header;
        
        if (($tpl->template_type ?? '') === 'carousel') {
            $meta['template_type'] = 'carousel';
            if (is_array($tpl->carousel_data)) {
                $meta['carousel_data'] = $tpl->carousel_data;
            }
        }

        $res = $this->dispatcher->sendRaw([
            'to_number'    => $to,
            'from_number'  => $fromNumber, // chosen device (routes the send there); null = Auto
            'body'         => $rendered,
            'meta'         => $meta ?: null,
            'workspace_id' => $workspaceId,
        ], $userId, 'W');

        return [
            'ok'          => (bool) ($res['ok'] ?? false) && empty($res['local_only']),
            'engine'      => 'baileys',
            'provider_id' => $res['provider_id'] ?? null,
            'error'       => ($res['ok'] ?? false) ? (($res['local_only'] ?? false) ? ($res['reason'] ?? 'No connected device') : null) : ($res['error'] ?? 'Baileys send failed'),
        ];
    }

    private function sendTwilio(WaTemplate $tpl, string $to, ?int $userId, int $workspaceId, string $rendered, array $positional): array
    {
        $meta = [];
        $useContentSid = !empty($tpl->twilio_content_sid);
        if ($useContentSid) {
            // Twilio ContentVariables are keyed by positional index "1","2"…
            $vars = [];
            foreach (array_values($positional['body'] ?? []) as $i => $v) {
                $vars[(string) ($i + 1)] = (string) $v;
            }
            $meta['template_vars'] = $vars;
        }

        $res = $this->dispatcher->sendRaw([
            'to_number'    => $to,
            'body'         => $useContentSid ? null : $rendered,
            'meta'         => $meta ?: null,
            'template_id'  => $useContentSid ? $tpl->id : null,
            'workspace_id' => $workspaceId,
        ], $userId, 'T');

        return [
            'ok'          => (bool) ($res['ok'] ?? false) && empty($res['local_only']),
            'engine'      => 'twilio',
            'provider_id' => $res['provider_id'] ?? null,
            'error'       => ($res['ok'] ?? false) ? (($res['local_only'] ?? false) ? ($res['reason'] ?? 'Twilio not configured') : null) : ($res['error'] ?? 'Twilio send failed'),
        ];
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Mirrors WhatsAppDispatcher::resolveProvider's workspace-first logic
     * (without a Message in hand): the workspace's primary connected
     * provider config wins if it's in the admin allow-list; otherwise the
     * admin default_send_method.
     */
    private function resolveEngine(int $workspaceId): WaProvider
    {
        $allowed = SystemSetting::get('allowed_send_methods', ['baileys', 'waba', 'twilio']);
        $allowed = is_array($allowed) ? $allowed : [$allowed];

        $cfg = WaProviderConfig::primaryForWorkspace($workspaceId)->first();
        if ($cfg && $cfg->isConnected() && in_array($cfg->provider, $allowed, true)) {
            return $cfg->providerEnum();
        }

        $default  = (string) SystemSetting::get('default_send_method', 'baileys');
        $resolved = WaProvider::tryFrom($default) ?? WaProvider::Baileys;
        if (!in_array($resolved->value, $allowed, true) && !empty($allowed)) {
            $resolved = WaProvider::tryFrom((string) $allowed[0]) ?? WaProvider::Baileys;
        }
        return $resolved;
    }

    /**
     * Replace named placeholders ({{name}}, {{order_number}}, …) in the
     * body from $ctx (case-insensitive). Numeric placeholders ({{1}}) are
     * replaced from $ctx['_positional'] so the Baileys plain-text render
     * matches the WABA positional params. Unknown tokens are stripped to
     * avoid leaking raw {{…}} to the recipient.
     */
    private function renderBody(string $body, array $ctx): string
    {
        if ($body === '') return $body;
        $positional = array_values($ctx['_positional'] ?? []);

        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($ctx, $positional) {
            $key = $m[1];
            if (ctype_digit($key)) {
                $idx = (int) $key - 1;
                return $positional[$idx] ?? '';
            }
            foreach ($ctx as $k => $v) {
                if (is_string($k) && strcasecmp($k, $key) === 0 && is_scalar($v)) {
                    return (string) $v;
                }
            }
            return '';
        }, $body);
    }

    /**
     * Build the positional body var array for WABA/Twilio templates.
     * Counts the distinct {{N}} placeholders in the body and fills each
     * from $ctx['_positional']. Returns ['body'=>[v1,v2,…]] (TemplateSender
     * shape) — empty when the template has no numeric body params.
     */
    private function positionalVars(string $body, array $ctx): array
    {
        if (!preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m)) {
            return [];
        }
        $max = 0;
        foreach ($m[1] as $n) $max = max($max, (int) $n);
        if ($max === 0) return [];

        $positional = array_values($ctx['_positional'] ?? []);
        $out = [];
        for ($i = 0; $i < $max; $i++) {
            $out[] = (string) ($positional[$i] ?? '');
        }
        return ['body' => $out];
    }
}

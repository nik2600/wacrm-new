<?php

namespace App\Http\Controllers;

use App\Models\SlackIntegration;
use App\Models\SlackIntegrationLog;
use App\Services\Integrations\ContactResolver;
use App\Services\WhatsAppDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Inbound Slack slash command: `/wa send <name>: <message>`.
 *
 * Slack POSTs application/x-www-form-urlencoded and requires an HTTP 200
 * within 3 seconds. We verify the request signature (HMAC-SHA256 of the RAW
 * body with the workspace's Signing Secret), ack immediately, then resolve
 * the contact + send the WhatsApp message in app()->terminating() and post
 * the result back to Slack's response_url.
 */
class SlackWebhookController extends Controller
{
    public function command(Request $request)
    {
        $raw         = $request->getContent();
        $ts          = (string) $request->header('X-Slack-Request-Timestamp', '');
        $sig         = (string) $request->header('X-Slack-Signature', '');
        $teamId      = (string) $request->input('team_id', '');
        $responseUrl = (string) $request->input('response_url', '');
        $text        = trim((string) $request->input('text', ''));

        $integration = $teamId
            ? SlackIntegration::where('team_id', $teamId)->where('status', 'active')->first()
            : null;

        if (!$integration) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text'          => 'This Slack workspace is not connected to WaDesk. Ask your admin to connect it under Integrations → Slack.',
            ]);
        }

        // Signature verification (mandatory).
        if (!$this->verifySignature((string) $integration->signing_secret, $ts, $raw, $sig)) {
            return response('invalid signature', 401);
        }

        $cmd = $integration->slash_command ?: '/wa';

        if ($text === '' || strtolower($text) === 'help') {
            return response()->json([
                'response_type' => 'ephemeral',
                'text'          => "Send a WhatsApp message from Slack.\nUsage: `{$cmd} send <name>: <message>`\nExample: `{$cmd} send Rahul: your order is ready`",
            ]);
        }

        [$name, $body] = $this->parse($text);
        if ($name === '' || $body === '') {
            return response()->json([
                'response_type' => 'ephemeral',
                'text'          => "Couldn't read that. Use: `{$cmd} send <name>: <message>`",
            ]);
        }

        // Do the actual send AFTER responding, to stay inside Slack's 3s window.
        $wsId = (int) $integration->workspace_id;
        $uId  = (int) $integration->user_id;
        $iid  = (int) $integration->id;
        // The operator-chosen sending channel ("engine:id"), so a multi-device
        // workspace sends from the number it picked, not just the primary.
        $sendFrom = (string) $integration->sendFrom();
        app()->terminating(function () use ($wsId, $uId, $iid, $name, $body, $responseUrl, $sendFrom) {
            $this->deliver($wsId, $uId, $iid, $name, $body, $responseUrl, $sendFrom);
        });

        return response()->json([
            'response_type' => 'ephemeral',
            'text'          => "On it — sending to *{$name}* on WhatsApp…",
        ]);
    }

    /** Resolve the contact, send the WhatsApp message, report back to Slack. */
    private function deliver(int $wsId, int $uId, int $iid, string $name, string $body, string $responseUrl, string $sendFrom = ''): void
    {
        $reply = function (string $t) use ($responseUrl) {
            if ($responseUrl === '') return;
            try {
                Http::acceptJson()->timeout(10)->post($responseUrl, ['response_type' => 'ephemeral', 'text' => $t]);
            } catch (\Throwable $e) { /* best-effort */ }
        };

        // Hot-path enforcement: honor the admin global toggle + the workspace's
        // plan flag even after connect (connect() checks both, but a workspace
        // could be disabled or downgraded afterwards).
        if (! \App\Models\SystemSetting::get('slack_enabled', true)) {
            SlackIntegrationLog::create(['integration_id' => $iid, 'workspace_id' => $wsId, 'event' => 'command', 'detail' => 'blocked: Slack disabled by admin', 'status' => 'error']);
            $reply('Slack → WhatsApp is currently disabled.');
            return;
        }
        $ws = \App\Models\Workspace::find($wsId);
        if ($ws && ! app(\App\Services\PlanLimitGuard::class)->hasFeature($ws, 'integration_slack')) {
            SlackIntegrationLog::create(['integration_id' => $iid, 'workspace_id' => $wsId, 'event' => 'command', 'detail' => 'blocked: plan lacks Slack', 'status' => 'error']);
            $reply('Your plan no longer includes the Slack integration.');
            return;
        }

        try {
            $r = app(ContactResolver::class)->resolve($wsId, $name);
            if (empty($r['number'])) {
                SlackIntegrationLog::create(['integration_id' => $iid, 'workspace_id' => $wsId, 'event' => 'command', 'detail' => 'no match: ' . $name, 'status' => 'no_match']);
                $reply("No WhatsApp contact found matching *{$name}*. Try the full name, or type a phone number instead.");
                return;
            }

            // Pin the operator-chosen sending channel (from_number + engine) so
            // a multi-device / multi-engine workspace sends from the number it
            // selected. Empty → the dispatcher resolves the workspace default.
            $sender = \App\Services\Integrations\SenderResolver::fromKey($wsId, $sendFrom);
            $params = [
                'to_number'    => $r['number'],
                'body'         => $body,
                'workspace_id' => $wsId,
            ];
            if ($sender['from_number'] !== '') $params['from_number'] = $sender['from_number'];
            if ($sender['provider'] !== '')    $params['provider']    = $sender['provider'];

            $res = app(WhatsAppDispatcher::class)->sendRaw($params, $uId, 'W');

            $ok = (bool) ($res['ok'] ?? false) && empty($res['local_only']);
            SlackIntegrationLog::create([
                'integration_id' => $iid, 'workspace_id' => $wsId, 'event' => 'command',
                'detail' => 'to ' . $r['label'], 'status' => $ok ? 'ok' : 'error',
            ]);
            SlackIntegration::where('id', $iid)->update(['last_used_at' => now()]);

            if ($ok) {
                $reply("Sent to *{$r['label']}* on WhatsApp.");
            } else {
                $why = ($res['local_only'] ?? false) ? ($res['reason'] ?? 'no connected WhatsApp device') : ($res['error'] ?? 'send failed');
                $reply("Couldn't send to *{$r['label']}*: {$why}");
            }
        } catch (\Throwable $e) {
            SlackIntegrationLog::create(['integration_id' => $iid, 'workspace_id' => $wsId, 'event' => 'error', 'detail' => substr($e->getMessage(), 0, 480), 'status' => 'error']);
            $reply('Something went wrong sending that message.');
        }
    }

    /** "send rahul: hi" / "rahul: hi" / "rahul hi" → [name, body]. */
    private function parse(string $text): array
    {
        $t = preg_replace('/^\s*send\s+/i', '', $text);
        if (str_contains($t, ':')) {
            [$name, $body] = explode(':', $t, 2);
            return [trim($name), trim($body)];
        }
        $parts = preg_split('/\s+/', trim($t), 2);
        return [trim($parts[0] ?? ''), trim($parts[1] ?? '')];
    }

    private function verifySignature(string $secret, string $ts, string $raw, string $sig): bool
    {
        if ($secret === '' || $ts === '' || $sig === '') return false;
        if (abs(time() - (int) $ts) > 300) return false;           // 5-minute replay window
        $calc = 'v0=' . hash_hmac('sha256', 'v0:' . $ts . ':' . $raw, $secret);
        return hash_equals($calc, $sig);
    }
}

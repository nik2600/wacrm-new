<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\WaProviderConfig;
use App\Enums\WaProvider;

/**
 * Twilio MessageStatus webhook receiver.
 *
 * Twilio POSTs here every time an outbound message changes state:
 * queued → sent → delivered → read (when read-receipts enabled) → undelivered/failed.
 * Without this endpoint WaDesk's Twilio broadcasts/sends stayed frozen at
 * `sent` forever because Twilio only fires delivery events when the
 * caller registers a StatusCallback URL on the send. We append that URL
 * via WhatsAppDispatcher::dispatchTwilio + InboxDispatcher::dispatchTwilio
 * + node/utils/helpers.js::sendMessageViaTwilioApi.
 *
 * Auth: Twilio signs the request with HMAC-SHA1 over (URL + sorted form
 * fields) using the workspace's AuthToken. We validate
 * `X-Twilio-Signature` so a casual probe can't fake delivery events.
 *
 * Body fields we read (form-encoded):
 *   MessageSid       — `SM…` / `MM…` outbound message id
 *   MessageStatus    — queued | sent | delivered | read | undelivered | failed | received
 *   AccountSid       — used to find the matching workspace
 *   ErrorCode        — present on failed/undelivered
 *   ErrorMessage     — human-readable
 *   From / To        — `whatsapp:+E164`
 */
class TwilioStatusController extends Controller
{
    public function handle(Request $request): Response
    {
        $params = $request->all();
        $sig    = (string) $request->header('X-Twilio-Signature', '');
        $accountSid = (string) ($params['AccountSid'] ?? '');
        $messageSid = (string) ($params['MessageSid'] ?? '');
        $status     = strtolower((string) ($params['MessageStatus'] ?? ''));

        if ($messageSid === '' || $status === '') {
            return response('missing fields', 400);
        }

        // Resolve workspace by AccountSid so each tenant validates against
        // its own AuthToken. A platform-shared installation may have many
        // Twilio configs; match the one whose creds.account_sid matches.
        $workspaceId = null;
        $authToken   = '';
        if ($accountSid !== '') {
            $cfgs = WaProviderConfig::query()
                ->where('provider', WaProvider::Twilio->value)
                ->where('status', WaProviderConfig::STATUS_CONNECTED)
                ->get();
            foreach ($cfgs as $cfg) {
                $creds = $cfg->creds();
                if (($creds['account_sid'] ?? '') === $accountSid) {
                    $workspaceId = (int) $cfg->workspace_id;
                    $authToken   = (string) ($creds['auth_token'] ?? '');
                    break;
                }
            }
        }
        // Fallback to admin-default creds for legacy single-tenant installs.
        if ($authToken === '') {
            $authToken = (string) \App\Models\SystemSetting::get('twilio_auth_token', env('TWILIO_AUTH_TOKEN', ''));
        }

        // Validate Twilio signature. Twilio's algorithm: HMAC-SHA1 over
        // the full URL + alphabetically-sorted form fields concatenated
        // as key+value, base64-encoded. We rebuild the same string and
        // compare against the supplied X-Twilio-Signature.
        // FAIL CLOSED: once we can resolve an auth token, a valid, PRESENT
        // X-Twilio-Signature is mandatory — a missing header no longer skips
        // verification (which previously let an attacker forge delivery/read/
        // failed states just by omitting the header).
        if ($authToken !== '') {
            if ($sig === '') {
                Log::warning('[TWILIO-STATUS] missing X-Twilio-Signature (auth token resolved)', [
                    'message_sid' => $messageSid,
                    'workspace_id' => $workspaceId,
                ]);
                return response('missing signature', 403);
            }
            $url = $request->fullUrl();
            ksort($params);
            $data = $url;
            foreach ($params as $k => $v) {
                $data .= $k . (is_array($v) ? json_encode($v) : (string) $v);
            }
            $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));
            if (!hash_equals($expected, $sig)) {
                Log::warning('[TWILIO-STATUS] signature mismatch', [
                    'message_sid' => $messageSid,
                    'workspace_id' => $workspaceId,
                ]);
                return response('invalid signature', 403);
            }
        }

        // Map Twilio status → WaDesk's canonical status enum.
        $mapped = match ($status) {
            'queued', 'accepted', 'sending', 'sent'  => 'sent',
            'delivered'                              => 'delivered',
            'read'                                   => 'read',
            'failed', 'undelivered'                  => 'failed',
            default                                  => $status,
        };
        $errorMsg = ($status === 'failed' || $status === 'undelivered')
            ? trim(($params['ErrorCode'] ?? '') . ' ' . ($params['ErrorMessage'] ?? ''))
            : null;

        $now = Carbon::now();
        $updates = ['status' => $mapped];
        if ($mapped === 'delivered') $updates['delivered_at'] = $now;
        if ($mapped === 'read')      $updates['read_at']      = $now;
        if ($errorMsg !== null && $errorMsg !== '') {
            $updates['error_message'] = mb_substr($errorMsg, 0, 255);
        }

        $touched = 0;

        // 1. broadcast_contacts — broadcast recipients
        try {
            $touched += DB::table('broadcast_contacts')
                ->where('whatsapp_message_id', $messageSid)
                ->update($updates);
        } catch (\Throwable $e) { /* table may not exist in older installs */ }

        // 2. messages — chat composer + flow sends
        try {
            $msgUpdates = $updates;
            if (isset($msgUpdates['error_message'])) {
                $msgUpdates['failure_reason'] = $msgUpdates['error_message'];
                unset($msgUpdates['error_message']);
            }
            $touched += DB::table('messages')
                ->where('from_number', $messageSid)
                ->orWhere('platform_message_id', $messageSid)
                ->update($msgUpdates);
        } catch (\Throwable $e) {}

        // 3. inbox_messages — team-inbox replies
        try {
            $touched += DB::table('inbox_messages')
                ->where('wa_message_id', $messageSid)
                ->update($updates);
        } catch (\Throwable $e) {}

        // 4. wa_orders — order confirmations sent via Twilio template
        try {
            $touched += DB::table('wa_orders')
                ->where('whatsapp_message_id', $messageSid)
                ->update($updates);
        } catch (\Throwable $e) {}

        // 5. wp_campaign_contacts — CAMPAIGN recipients.
        //
        // This table was missing from the list, so Twilio delivery/read
        // receipts never reached a campaign. The MessageSid IS stored here at
        // send time (WaCampaignsController: 'whatsapp_message_id' =>
        // $result['provider_id']) — nothing ever queried it with that SID, so
        // a Twilio campaign's Delivered/Read counters were permanently 0 while
        // the same receipts updated broadcasts fine.
        try {
            $campaignIds = DB::table('wp_campaign_contacts')
                ->where('whatsapp_message_id', $messageSid)
                ->pluck('campaign_id')
                ->unique()
                ->values();

            if ($campaignIds->isNotEmpty()) {
                $touched += DB::table('wp_campaign_contacts')
                    ->where('whatsapp_message_id', $messageSid)
                    ->update($updates);

                // Roll the per-recipient rows up into the campaign counters —
                // the same recompute the WABA webhook calls, so both engines
                // land on one implementation instead of two.
                foreach ($campaignIds as $cid) {
                    try {
                        \App\Models\WpCampaign::find($cid)?->recomputeAggregates();
                    } catch (\Throwable $e) {
                        Log::warning('[TWILIO-STATUS] campaign recompute failed', [
                            'campaign_id' => $cid, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {}

        Log::info('[TWILIO-STATUS] applied', [
            'message_sid'  => $messageSid,
            'status'       => $mapped,
            'workspace_id' => $workspaceId,
            'rows_touched' => $touched,
        ]);

        // Twilio expects 200/204 within 15s; anything else triggers retries.
        return response('', 204);
    }
}

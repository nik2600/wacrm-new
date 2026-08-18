<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin → Meta Ads → Keys.
 *
 * Platform-wide FALLBACK credentials for Click-to-WhatsApp ads. A
 * workspace's own Meta Ads keys (entered on /meta-ads, stored on its
 * WaProviderConfig.meta_json) always take priority; these admin values
 * fill any field the workspace left blank — so a single shared ad
 * account can back every workspace that hasn't connected its own.
 *
 * MetaGraphClient::adminFallbackKeys() reads exactly these keys. The
 * access token is encrypted at rest (SystemSetting::ENCRYPTED_KEYS).
 */
class MetaAdsKeysController extends Controller
{
    public function edit()
    {
        return view('admin.meta-ads.keys', [
            // Never echo the real token back into the form — show only
            // whether one is set (mirrors CRIT-6/CRIT-7 secret masking).
            'hasToken'      => SystemSetting::get('meta_ads.token', '') !== '',
            'adAccountId'   => (string) SystemSetting::get('meta_ads.ad_account_id', ''),
            'pageId'        => (string) SystemSetting::get('meta_ads.page_id', ''),
            'phone'         => (string) SystemSetting::get('meta_ads.phone', ''),
            'wabaId'        => (string) SystemSetting::get('meta_ads.waba_id', ''),
            'phoneNumberId' => (string) SystemSetting::get('meta_ads.phone_number_id', ''),
            'graphVersion'  => (string) SystemSetting::get('meta_ads_graph_api_version', 'v23.0'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token'           => ['nullable', 'string', 'max:512'],
            'ad_account_id'   => ['nullable', 'string', 'max:64'],
            'page_id'         => ['nullable', 'string', 'max:64'],
            'phone'           => ['nullable', 'string', 'max:32'],
            'waba_id'         => ['nullable', 'string', 'max:64'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'graph_version'   => ['nullable', 'string', 'max:8'],
            'clear_token'     => ['nullable', 'in:0,1'],
        ]);

        // Token: leave untouched when the field is blank (the form never
        // pre-fills it), unless the admin explicitly ticked "clear".
        if (!empty($data['clear_token'])) {
            SystemSetting::set('meta_ads.token', '', 'string');
        } elseif (!empty($data['token'])) {
            SystemSetting::set('meta_ads.token', trim($data['token']), 'string');
        }

        // Ad account: store WITHOUT the act_ prefix; the client adds it.
        $acct = preg_replace('/^act_/', '', trim((string) ($data['ad_account_id'] ?? '')));
        SystemSetting::set('meta_ads.ad_account_id',   $acct,                                          'string');
        SystemSetting::set('meta_ads.page_id',         trim((string) ($data['page_id'] ?? '')),        'string');
        SystemSetting::set('meta_ads.phone',           trim((string) ($data['phone'] ?? '')),          'string');
        SystemSetting::set('meta_ads.waba_id',         trim((string) ($data['waba_id'] ?? '')),        'string');
        SystemSetting::set('meta_ads.phone_number_id', trim((string) ($data['phone_number_id'] ?? '')),'string');
        if (!empty($data['graph_version'])) {
            SystemSetting::set('meta_ads_graph_api_version', trim($data['graph_version']), 'string');
        }

        Audit::log('admin.meta_ads_keys.updated', [
            'meta' => [
                'ad_account_set'     => $acct !== '',
                'page_set'           => trim((string) ($data['page_id'] ?? '')) !== '',
                'token_changed'      => !empty($data['token']) || !empty($data['clear_token']),
                'token_cleared'      => !empty($data['clear_token']),
            ],
        ]);

        return back()->with('success', 'Meta Ads fallback keys saved.');
    }
}

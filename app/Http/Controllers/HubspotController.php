<?php

namespace App\Http\Controllers;

use App\Models\HubspotIntegration;
use App\Services\Hubspot\HubspotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class HubspotController extends Controller
{
    public function __construct(private readonly HubspotService $hubspot) {}

    /** GET /hubspot — connect screen if not linked, dashboard otherwise. */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        $integration = $wsId ? HubspotIntegration::where('workspace_id', $wsId)->latest('id')->first() : null;

        // Real sync stats from the activity log — drives the dynamic KPI
        // tiles on the connected view (no hardcoded numbers).
        $stats = ['created' => 0, 'updated' => 0, 'failed' => 0, 'last' => null];
        if ($integration) {
            $logs = $integration->logs();
            $stats['created'] = (clone $logs)->where('event_type', 'deal.created')->where('status', 'sent')->count();
            $stats['updated'] = (clone $logs)->where('event_type', 'deal.updated')->where('status', 'sent')->count();
            $stats['failed']  = (clone $logs)->where('status', 'failed')->count();
            $stats['last']    = (clone $logs)->latest('created_at')->value('created_at');
        }

        return view('user.hubspot.dashboard', [
            'integration' => $integration,
            'appEnabled'  => $this->hubspot->isEnabled() && $this->hubspot->clientId() !== '',
            'recentLogs'  => $integration
                ? $integration->logs()->latest('created_at')->limit(15)->get()
                : collect(),
            'stats'       => $stats,
        ]);
    }

    /** POST /hubspot/connect — kick the OAuth redirect. */
    public function startOAuth(Request $request)
    {
        if (!$this->hubspot->isEnabled() || $this->hubspot->clientId() === '') {
            return back()->with('error', 'HubSpot integration is not configured. Ask your admin to enable it.');
        }
        $state = Str::random(40);
        // PKCE (RFC 7636): store the verifier server-side; send only the S256
        // challenge to HubSpot now, then prove ownership with the verifier at
        // the token exchange. HubSpot's app requires this or the connect fails
        // with "the code challenge parameter is missing".
        $pkce = \App\Services\Hubspot\HubspotService::generatePkce();
        session([
            'hubspot_oauth_state'   => $state,
            'hubspot_pkce_verifier' => $pkce['verifier'],
        ]);
        return redirect()->away($this->hubspot->authorizeUrl($state, $pkce['challenge']));
    }

    /** GET /hubspot/oauth/callback — verify state, exchange code, persist. */
    public function oauthCallback(Request $request)
    {
        // Plan: integration must be enabled on the workspace's plan.
        \App\Services\PlanLimitGuard::feature($request->user()?->currentWorkspace, 'integration_hubspot');

        $state = (string) $request->query('state', '');
        if (!$state || $state !== session('hubspot_oauth_state')) {
            return redirect('/hubspot')->with('error', 'OAuth state mismatch.');
        }

        $code = (string) $request->query('code', '');
        // PKCE: prove ownership of the challenge sent at /oauth/authorize.
        $verifier = (string) session('hubspot_pkce_verifier', '');
        $exchange = $this->hubspot->exchangeCode($code, $verifier ?: null);
        if (!($exchange['success'] ?? false)) {
            return redirect('/hubspot')->with('error', 'HubSpot OAuth failed: ' . ($exchange['error'] ?? 'unknown'));
        }

        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        if (!$wsId) return redirect('/hubspot')->with('error', 'No workspace selected.');

        $portal = $this->hubspot->getPortalInfo($exchange['access_token']);

        HubspotIntegration::updateOrCreate(
            ['workspace_id' => $wsId, 'portal_id' => $portal['portal_id'] ?? ''],
            [
                'user_id'                 => $user->id,
                'portal_name'             => $portal['portal_name'] ?? null,
                'portal_email'            => $portal['portal_email'] ?? null,
                'access_token'            => $exchange['access_token'],
                'refresh_token'           => $exchange['refresh_token'] ?? '',
                'access_token_expires_at' => now()->addSeconds($exchange['expires_in'] ?? 1800),
                'scopes'                  => $this->hubspot->scopes(),
                'status'                  => 'active',
                'last_verified_at'        => now(),
                'connected_at'            => now(),
            ],
        );

        session()->forget(['hubspot_oauth_state', 'hubspot_pkce_verifier']);
        return redirect('/hubspot')->with('success', 'HubSpot connected.');
    }

    public function disconnect(int $id)
    {
        $user = Auth::user();
        $integration = $user
            ? HubspotIntegration::where('workspace_id', $user->current_workspace_id)->find($id)
            : null;
        if (!$integration) abort(404);
        $integration->delete();
        return redirect('/hubspot')->with('success', 'HubSpot disconnected.');
    }
}

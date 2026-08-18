<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GoogleCalendarOAuthController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $gcal) {}

    /** POST /appointments/oauth/google/start */
    public function start(Request $request)
    {
        // Fail-fast on incomplete platform config. Without the secret
        // check, an admin who pasted only the client_id would let users
        // complete consent and then get a cryptic "Google OAuth failed"
        // on the callback (exchangeCode needs the secret). Catching it
        // here surfaces a clear "ask your admin" message before Google
        // ever sees the user.
        if (!$this->gcal->isEnabled() || $this->gcal->clientId() === '' || $this->gcal->clientSecret() === '') {
            return back()->with('error', 'Google integration isn\'t configured. Ask your admin to set the OAuth Client ID + Client secret at /admin/settings/google-calendar.');
        }
        $state = Str::random(40);
        // Remember which page the connect button was clicked from so the
        // callback can bounce back there (e.g. /google-account vs the
        // legacy /appointments/settings). Falls back to /appointments/settings.
        $returnTo = (string) ($request->headers->get('referer') ?: '/appointments/settings');
        session([
            'gcal_oauth_state'     => $state,
            'gcal_oauth_ws'        => (int) Auth::user()?->current_workspace_id,
            'gcal_oauth_return_to' => $returnTo,
        ]);
        return redirect()->away($this->gcal->authorizeUrl($state));
    }

    /**
     * GET /appointments/oauth/google/callback — verifies state,
     * exchanges code, persists tokens into workspaces.appointment_settings.
     */
    public function callback(Request $request)
    {
        // Plan: integration must be enabled on the workspace's plan.
        \App\Services\PlanLimitGuard::feature($request->user()?->currentWorkspace, 'integration_google_calendar');

        $state = (string) $request->query('state', '');
        if (!$state || $state !== session('gcal_oauth_state')) {
            return redirect('/appointments/settings')->with('error', 'OAuth state mismatch.');
        }

        if ($request->query('error')) {
            session()->forget(['gcal_oauth_state', 'gcal_oauth_ws']);
            return redirect('/appointments/settings')->with('error', 'Google authorization was cancelled.');
        }

        $code = (string) $request->query('code', '');
        $exchange = $this->gcal->exchangeCode($code);
        if (!($exchange['success'] ?? false)) {
            return redirect('/appointments/settings')->with('error', 'Google OAuth failed: ' . ($exchange['error'] ?? 'unknown'));
        }

        $wsId = (int) (session('gcal_oauth_ws') ?: Auth::user()?->current_workspace_id);
        $workspace = $wsId ? Workspace::find($wsId) : null;
        if (!$workspace) {
            return redirect('/appointments/settings')->with('error', 'No workspace context for callback.');
        }

        // Fetch the user's Google profile so the /google-account page
        // can show email + avatar. DEFAULT_SCOPES explicitly requests
        // openid + userinfo.email + userinfo.profile (a resource scope like
        // calendar does NOT grant userinfo), so this returns the basics.
        // Stays best-effort: an older connection without those scopes just
        // yields an empty profile, never a hard failure.
        $profile = [];
        try {
            $info = \Illuminate\Support\Facades\Http::withToken($exchange['access_token'])
                ->timeout(10)
                ->acceptJson()
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');
            if ($info->successful()) {
                $p = (array) $info->json();
                $profile = [
                    'email'   => (string) ($p['email']   ?? ''),
                    'name'    => (string) ($p['name']    ?? ''),
                    'picture' => (string) ($p['picture'] ?? ''),
                    'sub'     => (string) ($p['sub']     ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            \Log::warning('[GCAL] userinfo fetch failed: ' . $e->getMessage());
        }

        $settings = $workspace->appointment_settings ?? [];
        $settings['google_oauth'] = array_merge($settings['google_oauth'] ?? [], [
            'access_token'  => $exchange['access_token'],
            'refresh_token' => $exchange['refresh_token']
                ?: ($settings['google_oauth']['refresh_token'] ?? ''),
            'expires_at'    => now()->addSeconds($exchange['expires_in'])->toIso8601String(),
            'scope'         => $exchange['scope'],
            'scopes'        => $exchange['scope'] ? preg_split('/\s+/', (string) $exchange['scope']) : [],
            'connected_at'  => now()->toIso8601String(),
            'profile'       => $profile,
        ]);
        // Default calendar selection — if the workspace doesn't have
        // one yet, set it to 'primary' so Meet links can be minted
        // immediately without forcing a second step. Operator can pick
        // a different calendar in /appointments/settings.
        if (empty($settings['google_oauth']['calendar_id'])) {
            $settings['google_oauth']['calendar_id'] = 'primary';
        }
        $workspace->appointment_settings = $settings;
        $workspace->save();

        // Bounce back to where the connect button was clicked. /more →
        // /google-account → consent → /google-account. Legacy
        // /appointments/settings → /appointments/settings.
        $returnTo = (string) (session('gcal_oauth_return_to') ?: '/appointments/settings');
        session()->forget(['gcal_oauth_state', 'gcal_oauth_ws', 'gcal_oauth_return_to']);
        // Only allow same-origin redirects to prevent open-redirect.
        if (!str_starts_with($returnTo, '/')) {
            $returnTo = '/appointments/settings';
        }
        return redirect($returnTo)->with('success', 'Google account connected — Calendar, Meet, and inbox composer are ready.');
    }

    /** POST /appointments/oauth/google/disconnect */
    public function disconnect(Request $request)
    {
        $wsId = (int) Auth::user()?->current_workspace_id;
        $workspace = $wsId ? Workspace::find($wsId) : null;
        if (!$workspace) return back()->with('error', 'No workspace.');

        $settings = $workspace->appointment_settings ?? [];
        // Revoke the grant at Google BEFORE clearing local tokens. Without
        // this step the OAuth grant lives on indefinitely in the user's
        // myaccount.google.com/permissions, the refresh_token keeps
        // working until they manually revoke it there, and a reconnect
        // that returns no fresh refresh_token would silently reuse the
        // stale grant. Best-effort: log + proceed with local clear on
        // failure (a Google-side outage shouldn't trap the user).
        $tokenToRevoke = (string) (
            $settings['google_oauth']['refresh_token']
            ?? $settings['google_oauth']['access_token']
            ?? ''
        );
        if ($tokenToRevoke !== '') {
            try {
                $resp = \Illuminate\Support\Facades\Http::asForm()
                    ->timeout(10)
                    ->post(\App\Services\GoogleCalendar\GoogleCalendarService::REVOKE_URL, [
                        'token' => $tokenToRevoke,
                    ]);
                if (! $resp->successful()) {
                    \Illuminate\Support\Facades\Log::warning('[GoogleOAuth] revoke returned non-2xx', [
                        'workspace_id' => $wsId,
                        'status'       => $resp->status(),
                        'body'         => mb_substr((string) $resp->body(), 0, 200),
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[GoogleOAuth] revoke threw', [
                    'workspace_id' => $wsId,
                    'error'        => $e->getMessage(),
                ]);
                // Proceed — local clear still runs below.
            }
        }

        unset($settings['google_oauth']);
        $workspace->appointment_settings = $settings;
        $workspace->save();
        return back()->with('success', 'Google Calendar disconnected.');
    }
}

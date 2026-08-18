<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\GoogleCalendar\GoogleCalendarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Workspace-scoped Google account connection. The same OAuth token
 * powers the BookAppointment node, the GoogleMeet node, and the
 * Team inbox "Send Meet link" composer button — so this page is the
 * single canonical surface to manage that connection.
 *
 * Auth: members of the current workspace. OAuth start/disconnect
 * happen on the AppointmentGoogleOAuthController routes already
 * registered — we just link to them so connect-flow logic lives in
 * one place.
 */
class GoogleAccountController extends Controller
{
    public function __construct(private GoogleCalendarService $gcal) {}

    /** GET /google-account */
    public function index(): View
    {
        $user = Auth::user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);
        $workspace = $wsId ? Workspace::find($wsId) : null;

        $oauth = $workspace?->appointment_settings['google_oauth'] ?? [];
        $isConnected = !empty($oauth['access_token'] ?? null);
        // Calendar list is only useful when connected — saves an
        // unnecessary network call on the empty-state render.
        $calendars = $isConnected ? $this->gcal->listCalendars($workspace) : [];

        // The admin-side OAuth client_id has to be configured (in
        // system_settings) for the connect button to do anything.
        $appReady = $this->gcal->isEnabled() && $this->gcal->clientId() !== '';

        return view('user.google-account.index', [
            'workspace'   => $workspace,
            'isConnected' => $isConnected,
            'oauth'       => $oauth,
            'calendars'   => $calendars,
            'appReady'    => $appReady,
        ]);
    }
}

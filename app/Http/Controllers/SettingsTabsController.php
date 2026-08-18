<?php

namespace App\Http\Controllers;

use App\Models\AiProviderKey;
use App\Models\Workspace;
use App\Support\SecurityAuditLogger;
use App\Support\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Handles every save endpoint for the /settings tabs (branding, team,
 * notifications, AI keys, security, data, appearance). The General
 * tab is still owned by UserPagesController::settingsUpdate.
 *
 * Every method is owner-only or returns 403; team-side actions
 * (invite, change role) live on the existing WorkspaceMembersController.
 */
class SettingsTabsController extends Controller
{
    /* ─────────── BRANDING ─────────── */

    public function updateBranding(Request $request)
    {
        $ws = $this->ownerWorkspaceOrFail($request);

        $data = $request->validate([
            'brand_primary'    => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_accent'     => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_background' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo'             => ['nullable', 'image', 'max:2048'],
            'favicon'          => ['nullable', 'image', 'max:512'],
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding/' . $ws->id, 'public');
            $data['brand_logo_path'] = $path;
        }
        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('branding/' . $ws->id, 'public');
            $data['brand_favicon_path'] = $path;
        }
        unset($data['logo'], $data['favicon']);

        $ws->update($data);
        return back()->with('success', 'Branding updated.');
    }

    /* ─────────── NOTIFICATIONS ─────────── */

    public function updateNotifications(Request $request)
    {
        $ws = $this->ownerWorkspaceOrFail($request);

        $data = $request->validate([
            'prefs'              => ['nullable', 'array'],
            'prefs.*'            => ['array'],
            'prefs.*.inapp'      => ['nullable', 'in:0,1,on'],
            'prefs.*.email'      => ['nullable', 'in:0,1,on'],
            'prefs.*.slack'      => ['nullable', 'in:0,1,on'],
            'cat_prefs'          => ['nullable', 'array'],
            'cat_prefs.*'        => ['array'],
            'cat_prefs.*.inapp'  => ['nullable', 'in:0,1,on'],
            'cat_prefs.*.email'  => ['nullable', 'in:0,1,on'],
            'cat_prefs.*.slack'  => ['nullable', 'in:0,1,on'],
            'slack_webhook'      => ['nullable', 'url', 'max:255'],
        ]);

        // Normalize each (event, channel) and (category, channel) to a
        // strict bool. The hidden inputs in the form ensure that even
        // when a checkbox is unchecked we still receive the key with
        // value '0' — so "absent" means the user genuinely never sees
        // that event surface (theoretical edge case).
        $bool = static fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN);
        $prefs = [];
        foreach (($data['prefs'] ?? []) as $event => $channels) {
            $prefs[$event] = [
                'inapp' => $bool($channels['inapp'] ?? false),
                'email' => $bool($channels['email'] ?? false),
                'slack' => $bool($channels['slack'] ?? false),
            ];
        }
        $cats = [];
        foreach (($data['cat_prefs'] ?? []) as $cat => $channels) {
            $cats[$cat] = [
                'inapp' => $bool($channels['inapp'] ?? false),
                'email' => $bool($channels['email'] ?? false),
                'slack' => $bool($channels['slack'] ?? false),
            ];
        }
        $prefs['_categories'] = $cats;

        // Slack incoming-webhook URL — the destination for the Slack channel
        // toggles. Stored alongside prefs so no extra column is needed.
        $prefs['_slack_webhook'] = trim((string) ($data['slack_webhook'] ?? ''));

        $ws->update(['notification_prefs' => $prefs]);
        return back()->with('success', 'Notification preferences saved.');
    }

    /* ─────────── AI KEYS (BYOK) ─────────── */

    public function updateAiKey(Request $request, string $provider)
    {
        $ws = $this->ownerWorkspaceOrFail($request);
        $provider = strtolower($provider);

        // Platform admins are never plan-gated — they own the install, so they
        // can plug a workspace key in regardless of the package's BYOK flag.
        // End customers still need the allow_byok_ai_keys feature on their plan.
        $isPlatformAdmin = (bool) optional($request->user())->isAdmin();
        if (!$isPlatformAdmin && !(bool) $ws->effectiveLimit('allow_byok_ai_keys', false)) {
            return back()->with('error', 'Your plan does not include Bring Your Own Keys. Upgrade to use your own AI provider keys.');
        }

        $data = $request->validate([
            'api_key' => ['required', 'string', 'max:1024'],
        ]);

        AiProviderKey::updateOrCreate(
            ['workspace_id' => $ws->id, 'provider' => $provider],
            ['api_key' => $data['api_key'], 'is_active' => true],
        );
        return back()->with('success', ucfirst($provider) . ' key saved.');
    }

    public function removeAiKey(Request $request, string $provider)
    {
        $ws = $this->ownerWorkspaceOrFail($request);
        AiProviderKey::where('workspace_id', $ws->id)->where('provider', strtolower($provider))->delete();
        return back()->with('success', ucfirst($provider) . ' key removed. Falling back to admin key.');
    }

    /* ─────────── SECURITY · 2FA ─────────── */

    public function enableTwoFactor(Request $request)
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        if ($user->two_factor_enabled) {
            return back()->with('success', 'Two-factor authentication is already enabled.');
        }
        $secret = $request->session()->get('two_factor_settings_secret');
        if (!$secret) {
            return back()->with('error', 'Two-factor setup expired. Refresh the page to generate a new code.');
        }
        if (!TwoFactorService::verifyCode($secret, $request->input('code'))) {
            SecurityAuditLogger::log('two_factor_failed', 'failed', $user, $request);
            return back()->withErrors(['code' => 'The authentication code is invalid.']);
        }
        $user->forceFill([
            'two_factor_enabled'      => true,
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('two_factor_settings_secret');
        SecurityAuditLogger::log('two_factor_enabled', 'success', $user, $request);
        return back()->with('success', 'Two-factor authentication enabled.');
    }

    public function disableTwoFactor(Request $request)
    {
        $user = $request->user();
        $request->validate(['current_password' => ['required', 'string']]);

        if (!$user->two_factor_enabled) {
            return back()->with('success', 'Two-factor authentication is already disabled.');
        }
        if (!Hash::check($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Wrong password.']);
        }
        $user->forceFill([
            'two_factor_enabled'         => false,
            'two_factor_secret'          => null,
            'two_factor_confirmed_at'    => null,
            'two_factor_recovery_codes'  => null,
        ])->save();
        SecurityAuditLogger::log('two_factor_disabled', 'info', $user, $request);
        return back()->with('success', 'Two-factor authentication disabled.');
    }

    /* ─────────── SECURITY · SESSIONS ─────────── */

    public function revokeSession(Request $request, string $sessionId)
    {
        $user = $request->user();
        $current = $request->session()->getId();
        if ($sessionId === $current) {
            return back()->with('error', 'Use the logout button to end the current session.');
        }
        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();
        if ($deleted) {
            SecurityAuditLogger::log('session_revoked', 'success', $user, $request, ['session_id' => $sessionId]);
            return back()->with('success', 'Session revoked.');
        }
        return back()->with('error', 'Session not found.');
    }

    public function revokeAllOtherSessions(Request $request)
    {
        $user = $request->user();
        $current = $request->session()->getId();
        $deleted = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $current)
            ->delete();
        SecurityAuditLogger::log('all_sessions_revoked', 'success', $user, $request, ['count' => $deleted]);
        return back()->with('success', $deleted . ' session(s) signed out.');
    }

    /* ─────────── DATA ─────────── */

    public function exportData(Request $request, string $type)
    {
        $ws = $this->ownerWorkspaceOrFail($request);

        return match ($type) {
            'contacts'      => $this->csvDownload('contacts.csv', $this->streamContacts($ws)),
            'conversations' => $this->csvDownload('conversations.csv', $this->streamConversations($ws)),
            'messages'      => $this->csvDownload('messages.csv', $this->streamMessages($ws)),
            default         => back()->with('error', 'Unknown export type.'),
        };
    }

    public function destroyWorkspace(Request $request)
    {
        $ws = $this->ownerWorkspaceOrFail($request);
        $request->validate([
            'confirm_name'     => ['required', 'string'],
            'current_password' => ['required', 'string'],
        ]);
        $user = $request->user();
        if (!Hash::check($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Wrong password.']);
        }
        if (trim($request->input('confirm_name')) !== trim((string) $ws->name)) {
            return back()->withErrors(['confirm_name' => 'Workspace name does not match.']);
        }
        SecurityAuditLogger::log('workspace_deleted', 'info', $user, $request, ['workspace_id' => $ws->id, 'name' => $ws->name]);
        $ws->delete();
        return redirect('/account')->with('success', 'Workspace deleted.');
    }

    /* ─────────── APPEARANCE ─────────── */

    public function updateAppearance(Request $request)
    {
        $request->validate([
            'theme_preference' => ['required', 'in:paper,bright,dark'],
        ]);
        $request->user()->update(['theme_preference' => $request->input('theme_preference')]);
        return back()->with('success', 'Appearance updated.');
    }

    /**
     * Personal UX preferences (one form per toggle today, but the
     * controller validates each field independently so additions are
     * cheap). Auth-only — no owner check; this is per-user state.
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'auto_ai_summarize_enabled' => ['nullable', 'in:0,1,on'],
        ]);
        $request->user()->update([
            'auto_ai_summarize_enabled' => $request->boolean('auto_ai_summarize_enabled'),
        ]);
        return back()->with('success', 'Preferences updated.');
    }

    /* ─────────── DATA RESIDENCY (Sprint 9.3) ─────────── */

    public function updateResidency(Request $request)
    {
        $ws = $this->ownerWorkspaceOrFail($request);
        $request->validate([
            'data_residency' => ['required', 'in:any,eu_only,local'],
        ]);
        $ws->update(['data_residency' => $request->input('data_residency')]);
        SecurityAuditLogger::log('data_residency_changed', 'info', $request->user(), $request,
            ['workspace_id' => $ws->id, 'value' => $ws->data_residency]);
        return back()->with('success', 'Data residency updated. Translation routing will respect the new setting on the next inbound.');
    }

    /* ─────────── helpers ─────────── */

    private function ownerWorkspaceOrFail(Request $request): Workspace
    {
        $user = $request->user();
        $ws   = $user?->currentWorkspace;
        if (!$ws) abort(404, 'No workspace.');
        $isOwner = (int) $ws->owner_user_id === (int) $user->id;
        if (!$isOwner) abort(403, 'Owner only.');
        return $ws;
    }

    private function csvDownload(string $filename, \Generator $rows)
    {
        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function streamContacts(Workspace $ws): \Generator
    {
        yield ['id', 'name', 'mobile', 'email', 'status', 'created_at'];
        // WaDesk stores contacts per-user; "workspace contacts" =
        // contacts owned by any user in the workspace_user pivot.
        $userIds = DB::table('workspace_user')->where('workspace_id', $ws->id)->pluck('user_id');
        foreach (\App\Models\Contact::whereIn('user_id', $userIds)->cursor() as $c) {
            yield [$c->id, $c->name, $c->mobile, $c->email, $c->status ?? '', $c->created_at?->toIso8601String()];
        }
    }

    private function streamConversations(Workspace $ws): \Generator
    {
        yield ['id', 'channel', 'status', 'last_message_at', 'created_at'];
        foreach (\App\Models\Conversation::where('workspace_id', $ws->id)->cursor() as $c) {
            yield [$c->id, $c->channel ?? '', $c->status ?? '', $c->last_message_at?->toIso8601String(), $c->created_at?->toIso8601String()];
        }
    }

    private function streamMessages(Workspace $ws): \Generator
    {
        yield ['id', 'conversation_id', 'direction', 'body', 'created_at'];
        $convIds = \App\Models\Conversation::where('workspace_id', $ws->id)->pluck('id');
        foreach (\DB::table('inbox_messages')->whereIn('conversation_id', $convIds)->cursor() as $m) {
            yield [$m->id, $m->conversation_id, $m->direction ?? '', mb_substr((string) $m->body, 0, 500), $m->created_at];
        }
    }
}

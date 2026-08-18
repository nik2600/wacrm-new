<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\RealtimeSettings;
use Illuminate\Http\Request;

/**
 * /admin/settings/realtime — ONE platform-level Pusher app that powers the
 * Team Inbox's live feed for every workspace. Admin pastes the keys here; the
 * broadcaster flips from `log` to `pusher` at runtime (no .env edit). Users
 * never enter keys — they get real-time automatically once this is configured.
 */
class RealtimeSettingsController extends Controller
{
    public function index()
    {
        $c = RealtimeSettings::raw();
        return view('admin.realtime.index', [
            'enabled'   => (bool) \App\Models\SystemSetting::get('realtime_enabled', false),
            'app_id'    => $c['app_id'],
            'key'       => $c['key'],
            'cluster'   => $c['cluster'] ?: 'mt1',
            'hasSecret' => $c['secret'] !== '',
        ]);
    }

    public function save(Request $request)
    {
        $data = $this->validated($request);
        RealtimeSettings::save($data);
        \App\Services\Inbox\AuditLogger::platform(
            'settings.realtime.save', auth()->id(), null, 'setting', null,
            ['enabled' => (bool) ($data['realtime_enabled'] ?? false)]
        );

        return back()->with('success', __('Real-time settings saved.'));
    }

    /** Save, then fire a real event at Pusher to prove the credentials work. */
    public function test(Request $request)
    {
        $data = $this->validated($request);
        RealtimeSettings::save($data);

        $result = $this->probe();

        if ($request->expectsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 422);
        }
        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'realtime_enabled'  => ['nullable', 'boolean'],
            'pusher_app_id'     => ['nullable', 'string', 'max:64'],
            'pusher_app_key'    => ['nullable', 'string', 'max:191'],
            'pusher_app_secret' => ['nullable', 'string', 'max:191'],
            'pusher_cluster'    => ['nullable', 'string', 'max:32'],
        ]);
    }

    /** Live credential check — triggers a throwaway event on Pusher. */
    private function probe(): array
    {
        $c = RealtimeSettings::raw();
        if ($c['key'] === '' || $c['secret'] === '' || $c['app_id'] === '') {
            return ['ok' => false, 'message' => __('Fill in App ID, Key and Secret first.')];
        }
        if (! class_exists(\Pusher\Pusher::class)) {
            return ['ok' => false, 'message' => __('Pusher PHP SDK is not installed on the server.')];
        }
        try {
            $pusher = new \Pusher\Pusher($c['key'], $c['secret'], $c['app_id'], [
                'cluster' => $c['cluster'] ?: 'mt1',
                'useTLS'  => true,
            ]);
            $pusher->trigger('wadesk-realtime-test', 'ping', ['at' => now()->toIso8601String()]);
            return ['ok' => true, 'message' => __('Connected — Pusher accepted a test event. Real-time is live.')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => __('Pusher rejected the credentials: ') . $e->getMessage()];
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\WaProviderConfig;
use App\Services\PlanLimitGuard;
use App\Services\WarmerService;
use App\Services\WorkspaceEngine;
use Illuminate\Http\Request;

/**
 * WhatsApp Warmer — per-number warm-up for EVERY engine (Unofficial + WABA +
 * Twilio). The user configures each connected number's ramping daily budget,
 * send gaps, active hours, spintax, and sees its health score.
 *
 * Warming is primarily a ban-avoidance tool for Unofficial-API numbers. WABA /
 * Twilio are official channels where Meta enforces messaging-limit tiers
 * server-side, so the ramp here acts as volume PACING (protect quality rating /
 * stay under tier limits) rather than ban protection. Enforced on bulk sends by
 * WarmerService (Unofficial via WaCampaignsController::runCampaignNowPaced).
 */
class WarmerController extends Controller
{
    public function __construct(private WarmerService $warmer) {}

    public function index(Request $request)
    {
        $ws = $request->user()?->currentWorkspace;
        // Plan gate — throws PlanLimitReachedException on locked plans.
        PlanLimitGuard::feature($ws, 'access_whatsapp_warmer');
        $wsId = (int) ($ws?->id ?? 0);

        // EVERY enabled engine's connected numbers (senders() merges them). Each
        // sender key is "engine:id"; resolve it back to its model so the warmer
        // reads/writes that number's own warmer_config.
        $rows = WorkspaceEngine::senders($wsId)->map(function ($s) {
            $model = $this->modelForKey((string) ($s['key'] ?? ''));
            if (!$model) return null;
            $engine = $model instanceof WaProviderConfig ? (string) $model->provider : 'baileys';
            // Uniform display object so the blade reads the same fields per engine.
            $display = (object) [
                'id'          => $model->id,
                'device_name' => $model instanceof WaProviderConfig
                    ? ($model->display_label ?: strtoupper($engine) . ' #' . $model->id)
                    : ($model->device_name ?: 'Number'),
                'status'      => (string) ($model->status ?? ''),
            ];
            return [
                'key'       => (string) $s['key'],
                'engine'    => $engine,
                'device'    => $display,
                'cfg'       => $this->warmer->config($model),
                'budget'    => $this->warmer->dailyBudget($model),
                'remaining' => $this->warmer->remainingToday($model),
                'sent'      => $this->warmer->sentOn($model),
                'health'    => $this->warmer->healthScore($model),
                'phone'     => (string) ($s['phone'] ?? ''),
            ];
        })->filter()->values();

        return view('user.warmer.index', [
            'rows'     => $rows,
            'defaults' => WarmerService::DEFAULTS,
        ]);
    }

    public function update(Request $request, string $key)
    {
        $ws = $request->user()?->currentWorkspace;
        PlanLimitGuard::feature($ws, 'access_whatsapp_warmer');

        $model = $this->modelForKey($key);
        // Same ownership rule index() lists by. A strict workspace_id compare
        // 403'd every Unofficial number whose device row predates workspaces
        // (workspace_id NULL): senders() shows it via the orphan fallback, then
        // NULL === 2 failed here. WABA/Twilio never tripped it — their configs
        // always carry a workspace_id — which is why only Unofficial broke.
        abort_unless($model && $ws && WorkspaceEngine::ownsSender($model, (int) $ws->id), 403);

        $data = $request->validate([
            'enabled'      => 'nullable|boolean',
            'daily_base'   => 'required|integer|min:1|max:5000',
            'step_pct'     => 'required|integer|min:0|max:200',
            'step_days'    => 'required|integer|min:1|max:60',
            'max_daily'    => 'required|integer|min:1|max:100000',
            'gap_min'      => 'required|integer|min:0|max:3600',
            'gap_max'      => 'required|integer|min:0|max:3600',
            'active_start' => 'required|integer|min:0|max:23',
            'active_end'   => 'required|integer|min:0|max:24',
            'spintax'      => 'nullable|boolean',
        ]);

        // Merge over the existing config so `started_at` (and any future keys)
        // survive a save.
        $cfg = array_merge($this->warmer->config($model), [
            'enabled'      => $request->boolean('enabled'),
            'daily_base'   => (int) $data['daily_base'],
            'step_pct'     => (int) $data['step_pct'],
            'step_days'    => (int) $data['step_days'],
            'max_daily'    => (int) $data['max_daily'],
            'gap_min'      => (int) $data['gap_min'],
            'gap_max'      => max((int) $data['gap_min'], (int) $data['gap_max']),
            'active_start' => (int) $data['active_start'],
            'active_end'   => (int) $data['active_end'],
            'spintax'      => $request->boolean('spintax'),
        ]);

        // Stamp the warm-up start the FIRST time it's enabled — the ramp dates
        // from here. Leave it once set so re-saving doesn't reset the ramp.
        if ($cfg['enabled'] && empty($cfg['started_at'])) {
            $cfg['started_at'] = now()->toDateString();
        }

        $model->forceFill(['warmer_config' => $cfg])->save();

        return back()->with('warmer_status', 'Warmer settings saved.');
    }

    /**
     * Resolve a "engine:id" sender key to its underlying model — a Device for
     * Unofficial, a WaProviderConfig for WABA / Twilio.
     */
    private function modelForKey(string $key): Device|WaProviderConfig|null
    {
        if (!str_contains($key, ':')) {
            // Legacy bare-id (old form action) → treat as a Baileys device.
            return Device::find((int) $key);
        }
        [$engine, $id] = explode(':', $key, 2);
        $id = (int) $id;
        if ($engine === WorkspaceEngine::ENGINE_BAILEYS) {
            return Device::find($id);
        }
        return WaProviderConfig::where('id', $id)->where('provider', $engine)->first();
    }
}

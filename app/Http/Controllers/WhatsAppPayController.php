<?php

namespace App\Http\Controllers;

use App\Models\WaOrder;
use App\Models\WaProviderConfig;
use App\Models\WaStorefront;
use App\Models\WorkspacePaymentConfig;
use App\Services\Waba\WhatsAppPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * WhatsApp Pay (in-chat order_details payments) — WP-3/WP-4 surface.
 *
 *  index/store/destroy : merchant manages their WhatsApp-Manager "Direct Pay
 *                        Method" config name (region-gated to India).
 *  requestPayment      : the manual "Request payment on WhatsApp" action for an
 *                        order — sends the order_details charge message.
 */
class WhatsAppPayController extends Controller
{
    public function index(Request $request)
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);

        // Reconcile sweep (project policy: no cron) — re-check pending orders
        // against the lookup API, cache-gated to once/60s per workspace.
        if ($wsId && Cache::add("wapay:reconcile:{$wsId}", 1, 60)) {
            try { app(WhatsAppPayService::class)->reconcilePending($wsId); } catch (\Throwable $e) {}
        }

        $configs = WorkspacePaymentConfig::query()->forWorkspace($wsId)->orderByDesc('id')->get();
        $waba    = WaProviderConfig::query()->where('workspace_id', $wsId)->where('provider', 'waba')->first();

        // Region detect (best-effort): the WABA phone's country. Native in-chat
        // pay is India-only — surface clearly rather than hard-hide.
        $country  = $this->detectCountry($waba);
        $regionOk = WorkspacePaymentConfig::isCountrySupported($country);

        $sf = $wsId ? WaStorefront::where('workspace_id', $wsId)->first() : null;
        $cfg = $waba;

        return view('user.store.payments.index', compact('configs', 'waba', 'country', 'regionOk', 'sf', 'cfg'));
    }

    public function store(Request $request)
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);
        $data = $request->validate([
            'config_name'  => 'required|string|max:60',
            'payment_type' => 'required|in:' . implode(',', WorkspacePaymentConfig::PAYMENT_TYPES),
            'is_active'    => 'nullable|boolean',
            'auto_charge'  => 'nullable|boolean',
        ]);

        $waba = WaProviderConfig::query()->where('workspace_id', $wsId)->where('provider', 'waba')->first();

        WorkspacePaymentConfig::updateOrCreate(
            ['workspace_id' => $wsId, 'config_name' => trim($data['config_name'])],
            [
                'provider_config_id' => $waba?->id,
                'payment_type'       => $data['payment_type'],
                'country'            => 'IN',
                'currency'           => 'INR',
                'is_active'          => (bool) ($data['is_active'] ?? true),
                'meta_json'          => ['auto_charge' => $request->boolean('auto_charge')],
            ],
        );

        return back()->with('status', __('WhatsApp Pay configuration saved.'));
    }

    public function destroy(Request $request, int $id)
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);
        WorkspacePaymentConfig::query()->forWorkspace($wsId)->whereKey($id)->delete();
        return back()->with('status', __('Payment configuration removed.'));
    }

    /** WP-3 — manual "Request payment on WhatsApp" for one order. */
    public function requestPayment(Request $request, int $orderId)
    {
        $wsId  = (int) ($request->user()->current_workspace_id ?? 0);
        $order = WaOrder::query()->where('workspace_id', $wsId)->whereKey($orderId)->firstOrFail();

        $cfg = WorkspacePaymentConfig::query()->forWorkspace($wsId)->active()->first();
        if (!$cfg) {
            return back()->withErrors(['wapay' => __('Add a WhatsApp Pay configuration first (Store → Payments).')]);
        }

        $res = app(WhatsAppPayService::class)->sendOrderDetails($order, $cfg);
        return ($res['ok'] ?? false) === true
            ? back()->with('status', __('Payment request sent on WhatsApp.'))
            : back()->withErrors(['wapay' => __('Could not send payment request: ') . ($res['error'] ?? 'unknown')]);
    }

    /** Best-effort ISO-2 country from the WABA phone (dial-code → ISO). */
    private function detectCountry(?WaProviderConfig $waba): ?string
    {
        if (!$waba) return null;
        $phone = preg_replace('/\D+/', '', (string) ($waba->phone_number ?? ''));
        if ($phone === '') return null;
        // Cheap India check (the only fully-supported market) — +91.
        if (str_starts_with($phone, '91')) return 'IN';
        return null; // unknown → treated as unsupported, shown as "India only"
    }
}

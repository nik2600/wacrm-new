<?php

namespace App\Http\Controllers;

use App\Models\WaOrder;
use App\Services\WhatsAppDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WaOrderController extends Controller
{
    public function __construct(private readonly WhatsAppDispatcher $dispatcher) {}

    public function index(Request $request): View
    {
        $wsId = Auth::user()->current_workspace_id;
        $status = $request->string('status')->toString() ?: 'all';
        $source = $request->string('source')->toString() ?: 'all';
        $q      = trim((string) $request->string('q')->toString());

        $rows = WaOrder::forWorkspace($wsId)
            ->when($status !== 'all', fn ($qq) => $qq->where('status', $status))
            ->when($source !== 'all', fn ($qq) => $qq->where('source', $source))
            ->when($q !== '', fn ($qq) => $qq->where(function ($w) use ($q) {
                $w->where('customer_phone', 'like', "%{$q}%")
                  ->orWhere('customer_name', 'like', "%{$q}%");
            }))
            ->orderByDesc('created_at')->paginate(25)->withQueryString();

        // One grouped query instead of N per-status counts — also picks up the
        // natural-language-ordering statuses (pending / processing / completed).
        $byStatus = WaOrder::forWorkspace($wsId)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $counts = ['all' => (int) $byStatus->sum()];
        foreach (WaOrder::STATUSES as $st) {
            $counts[$st] = (int) ($byStatus[$st] ?? 0);
        }

        return view('user.store.orders.index', compact('rows', 'counts', 'status', 'source', 'q'));
    }

    public function show(int $id): View
    {
        $wsId = Auth::user()->current_workspace_id;
        $order = WaOrder::forWorkspace($wsId)->findOrFail($id);
        return view('user.store.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $order = WaOrder::forWorkspace($wsId)->findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', WaOrder::STATUSES),
            'notes'  => 'nullable|string|max:1000',
            'payment_link' => 'nullable|url|max:1024',
        ]);
        $statusChanged = (string) $order->status !== (string) $data['status'];
        $newLink       = trim((string) ($data['payment_link'] ?? ''));
        $order->fill($data)->save();

        // DM the BUYER directly. The status-change observer (WaOrder::updated)
        // ONLY posts into the customer's WhatsApp group — so 1v1/normal orders
        // got no notice, and the payment link reached NOBODY (this form used to
        // just save it). Send the status change + payment link to customer_phone.
        $this->notifyBuyer($order, $statusChanged, $newLink);

        return redirect()->route('user.store.orders.show', $order->id)->with('status', 'Order updated.');
    }

    /**
     * WhatsApp the buyer their order-status change and/or payment link directly.
     * Covers BOTH normal (1v1) and group orders — the group post is visibility,
     * the DM is the buyer's personal copy (a payment link can't live only in a
     * group). Localized to the customer's saved language. Best-effort; a send
     * failure never blocks the status save.
     */
    private function notifyBuyer(WaOrder $order, bool $statusChanged, string $paymentLink): void
    {
        $phone = trim((string) $order->customer_phone);
        if ($phone === '' || (!$statusChanged && $paymentLink === '')) return;

        $lang = is_array($order->meta_json) ? ($order->meta_json['customer_lang'] ?? null) : null;
        $svc  = app(\App\Services\Ordering\OrderingService::class);

        $lines = [];
        if ($statusChanged) {
            $lines[] = $svc->localizeTo("Your order #{$order->id} is now: " . ucfirst((string) $order->status), $lang);
        }
        if ($paymentLink !== '') {
            $lines[] = $svc->localizeTo("Here's your payment link for {$order->total_display}:", $lang) . "\n" . $paymentLink;
        }
        $body = trim(implode("\n\n", $lines));
        if ($body === '') return;

        try {
            $msg = \App\Models\Message::create([
                'user_id'      => Auth::id(),
                // Pin to the ORDER's workspace — NOT the operator's current
                // workspace. Without this the dispatcher resolved the device from
                // whatever workspace the operator happened to be viewing (e.g. a
                // second test workspace), so it sent from the wrong/no device and
                // the buyer never got the status/payment DM ("Failed to send").
                'workspace_id' => $order->workspace_id,
                'direction'    => 'out',
                'to_number'    => $phone,
                'body'         => $body,
                'status'       => 'pending',
            ]);
            $result = $this->dispatcher->send($msg);
            $msg->status         = ($result['ok'] ?? false) ? 'sent' : 'failed';
            $msg->failure_reason = $result['error'] ?? null;
            $msg->sent_at        = $msg->status === 'sent' ? now() : null;
            $msg->save();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ORDER] buyer status/payment DM failed: ' . $e->getMessage());
        }
    }

    public function sendPaymentLink(Request $request, int $id): JsonResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $order = WaOrder::forWorkspace($wsId)->findOrFail($id);
        $data = $request->validate(['payment_link' => 'required|url|max:1024']);

        $order->payment_link = $data['payment_link'];
        $order->save();

        $body = "Hi {$order->customer_name}, here's your payment link for *{$order->total_display}*:\n\n{$data['payment_link']}";
        $msg = \App\Models\Message::create([
            'user_id'      => Auth::id(),
            'workspace_id' => $order->workspace_id,   // route via the ORDER's device, not the operator's current workspace
            'direction'    => 'out',
            'to_number'    => $order->customer_phone,
            'body'         => $body,
            'status'       => 'pending',
        ]);
        $result = $this->dispatcher->send($msg);
        $msg->status = ($result['ok'] ?? false) ? 'sent' : 'failed';
        $msg->failure_reason = $result['error'] ?? null;
        $msg->sent_at = $msg->status === 'sent' ? now() : null;
        $msg->save();

        return response()->json(['ok' => $msg->status === 'sent', 'message_id' => $msg->id]);
    }

    /**
     * S2 — mint a REAL Razorpay payment link with the merchant's own keys
     * (storefront settings) and WhatsApp it to the customer. Falls back with
     * a helpful message if API keys aren't configured.
     */
    public function generatePaymentLink(int $id): JsonResponse
    {
        $wsId  = Auth::user()->current_workspace_id;
        $order = WaOrder::forWorkspace($wsId)->findOrFail($id);
        $sf    = \App\Models\WaStorefront::where('workspace_id', $wsId)
            ->when($order->storefront_id, fn ($q) => $q->where('id', $order->storefront_id))
            ->first();

        $pay = app(\App\Services\Storefront\StorefrontPaymentService::class);
        if (!$sf || !$pay->supportsLinks($sf)) {
            return response()->json([
                'ok' => false,
                'message' => 'Add your Razorpay API keys in Storefront settings (Payment) to auto-generate links, or paste one manually.',
            ], 422);
        }

        $callback = ($sf->custom_domain_verified && $sf->custom_domain ? 'https://' . $sf->custom_domain : url('/s/' . $sf->slug))
            . '/order/' . $order->recovery_token;
        $link = $pay->mintLink($sf, $order, $callback);
        if (!$link || empty($link['url'])) {
            return response()->json(['ok' => false, 'message' => 'Could not create the payment link. Check your Razorpay keys.'], 502);
        }

        $order->forceFill([
            'payment_link' => $link['url'],
            'meta_json'    => array_merge(is_array($order->meta_json) ? $order->meta_json : [], ['payment_link_id' => $link['id'] ?? null]),
        ])->save();

        // Deliver via WhatsApp (same path as a manual link).
        $body = "Hi {$order->customer_name}, here's your secure payment link for *{$order->total_display}*:\n\n{$link['url']}";
        $msg = \App\Models\Message::create([
            'user_id'      => Auth::id(),
            'workspace_id' => $order->workspace_id,   // route via the ORDER's device
            'direction'    => 'out',
            'to_number'    => $order->customer_phone,
            'body'         => $body,
            'status'       => 'pending',
        ]);
        $result = $this->dispatcher->send($msg);
        $msg->status = ($result['ok'] ?? false) ? 'sent' : 'failed';
        $msg->failure_reason = $result['error'] ?? null;
        $msg->sent_at = $msg->status === 'sent' ? now() : null;
        $msg->save();

        return response()->json(['ok' => true, 'url' => $link['url'], 'sent' => $msg->status === 'sent']);
    }

    public function destroy(int $id): RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        WaOrder::forWorkspace($wsId)->findOrFail($id)->delete();
        return redirect()->route('user.store.orders.index')->with('status', 'Order removed.');
    }
}

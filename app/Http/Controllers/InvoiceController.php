<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Per-order invoice surface. Renders a printable HTML invoice the
 * buyer can save as PDF via the browser's print dialog.
 *
 * Scoped strictly to the order's user_id — a buyer can only fetch
 * their own invoices. Admin gets to all orders via /admin/orders.
 */
class InvoiceController extends Controller
{
    public function download(Request $request, int $id)
    {
        // Use $request->user() (route-bound resolver), NOT Auth::user()
        // (session-bound) — they can differ in middleware/test contexts
        // and we don't want the auth check to silently fall through.
        $user  = $request->user();
        if (!$user) abort(401);
        $order = Order::query()->with(['package', 'workspace', 'user'])->findOrFail($id);

        if ((int) $order->user_id !== (int) $user->id) abort(403, 'Not your order.');

        // Pull the company/billing identity the admin configured at
        // /admin/checkout-settings (single source via Brand::billing()).
        // Keep the legacy name/address/tax_id keys the view expects, and
        // surface the richer reg_no/email/phone/tax_label too.
        $b = \App\Support\Brand::billing();
        $brand = [
            'name'      => $b['company'],
            'address'   => $b['address'],
            'tax_id'    => $b['tax_id'],
            'reg_no'    => $b['reg_no'],
            'email'     => $b['email'],
            'phone'     => $b['phone'],
            'tax_label' => $b['tax_label'],
            // Light-theme logo (invoice is always on white/print). Falls back
            // to the default-theme logo, then to null → wordmark fallback.
            'logo'      => \App\Support\Brand::logoUrl('paper'),
        ];

        return response()
            ->view('user.invoice.show', compact('order', 'brand'))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}

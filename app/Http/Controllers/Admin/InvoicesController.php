<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoicesController extends Controller
{
    public function index(Request $request): View
    {
        $statusF = (string) $request->query('status', 'all');
        $window  = $request->query('window', 'this_month');
        $q       = trim((string) $request->query('q', ''));

        [$from, $to] = $this->windowRange($window);

        // We treat the orders table as the "invoice" source for now —
        // every paid/pending order generates an INV-YYYY-MMNNNNN slug
        // computed in the view.
        $query = Order::query()->with('workspace')->whereBetween('created_at', [$from, $to]);

        // Status pills mapping.
        if ($statusF === 'paid')        $query->where('status', 'paid');
        if ($statusF === 'outstanding') $query->where('status', 'pending');
        if ($statusF === 'overdue')     $query->where('status', 'pending')->where('created_at', '<', now()->subDays(15));
        if ($statusF === 'refunded')    $query->where('status', 'refunded');
        if ($statusF === 'void')        $query->where('status', 'failed');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                  ->orWhere('customer_email', 'like', "%{$q}%")
                  ->orWhereHas('workspace', fn ($wq) => $wq->where('name', 'like', "%{$q}%"));
            });
        }

        $invoices = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        return view('admin.invoices.index', [
            'window'   => $window,
            'statusF'  => $statusF,
            'q'        => $q,
            'invoices' => $invoices,
            'stats'    => $this->kpis(),
            // Manual-invoice modal picker + default currency.
            'workspaces'      => Workspace::orderBy('name')->get(['id', 'name', 'currency']),
            'defaultCurrency' => strtoupper((string) (optional(\App\Support\FormatSettings::currencyFor())->code ?: 'USD')),
        ]);
    }

    /**
     * Create a MANUAL invoice — an admin-issued bill not tied to a checkout.
     * Invoices are backed by the orders table, so a manual invoice is just an
     * Order row stamped gateway_slug='manual'. The "Manual invoice" button on
     * the index opens the modal that POSTs here. Previously that button was a
     * dead stub (no route / no handler) — the reported "not working".
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id'   => ['required', 'integer', 'exists:workspaces,id'],
            'customer_name'  => ['nullable', 'string', 'max:191'],
            'customer_email' => ['nullable', 'email', 'max:191'],
            'description'    => ['required', 'string', 'max:500'],
            'amount'         => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'currency'       => ['required', 'string', 'size:3'],
            'status'         => ['required', 'in:pending,paid'],
            'issued_at'      => ['nullable', 'date'],
        ]);

        $ws   = Workspace::find($data['workspace_id']);
        $paid = $data['status'] === 'paid';
        $when = !empty($data['issued_at']) ? Carbon::parse($data['issued_at']) : now();

        $order = new Order([
            'order_number'    => Order::generateOrderNumber(),
            'workspace_id'    => $ws->id,
            'user_id'         => $ws->owner_user_id,
            'gateway_slug'    => 'manual',
            'currency'        => strtoupper($data['currency']),
            'amount'          => $data['amount'],
            'total_amount'    => $data['amount'],
            'status'          => $data['status'],
            'paid_at'         => $paid ? $when : null,
            'customer_name'   => $data['customer_name'] ?: ($ws->name ?? null),
            'customer_email'  => $data['customer_email'] ?: null,
            'gateway_payload' => ['manual' => true, 'description' => $data['description'], 'issued_by' => Auth::id()],
        ]);
        // created_at doubles as the invoice ISSUE date in the view, so honour a
        // back-dated issue date. Set it BEFORE save so updateTimestamps() leaves
        // it alone (it only stamps created_at when not already dirty).
        $order->created_at = $when;
        $order->save();

        return redirect()->route('admin.invoices.view', $order->id)
            ->with('status', 'Manual invoice ' . $order->order_number . ' created.');
    }

    /** Mark an outstanding (pending) invoice as paid. */
    public function markPaid(int $id): RedirectResponse
    {
        $order = Order::findOrFail($id);
        if ($order->status !== 'paid') {
            $order->status  = 'paid';
            $order->paid_at = $order->paid_at ?: now();
            $order->save();
        }
        return back()->with('status', 'Invoice ' . $order->order_number . ' marked as paid.');
    }

    /**
     * Void an invoice — a non-destructive cancel that keeps the row for the
     * audit trail but flips it to the "void" bucket (status='failed', which the
     * index maps to the Void pill). Paid invoices are refunded instead of voided
     * elsewhere, so we only void unpaid ones.
     */
    public function void(int $id): RedirectResponse
    {
        $order = Order::findOrFail($id);
        if ($order->status === 'paid') {
            return back()->with('error', 'A paid invoice cannot be voided — issue a refund instead.');
        }
        $order->status = 'failed';
        $order->save();
        return back()->with('status', 'Invoice ' . $order->order_number . ' voided.');
    }

    /**
     * Permanently delete an invoice row. Reserved for manual / erroneous
     * invoices — a real paid order is part of the revenue record and must be
     * voided/refunded, never deleted, so we block deleting paid ones.
     */
    public function destroy(int $id): RedirectResponse
    {
        $order = Order::findOrFail($id);
        if ($order->status === 'paid') {
            return back()->with('error', 'A paid invoice cannot be deleted — void or refund it instead.');
        }
        $number = $order->order_number;
        $order->delete();
        return redirect()->route('admin.invoices.index')
            ->with('status', 'Invoice ' . $number . ' deleted.');
    }

    public function show(string $id): View
    {
        $invoice = Order::with(['workspace', 'user', 'gateway'])->findOrFail($id);
        $package = $invoice->package_id ? Package::find($invoice->package_id) : null;
        return view('admin.invoices.view', [
            'invoice' => $invoice,
            'package' => $package,
            'billing' => \App\Support\Brand::billing(),
        ]);
    }

    private function windowRange(string $window): array
    {
        $now = now();
        return match ($window) {
            // "All time" — invoices issued before the 1st of the current month
            // were invisible under the this_month default, which read as
            // "invoices not appearing". This window shows every row.
            'all'          => [$now->copy()->subYears(50),      $now->copy()->endOfDay()],
            'last_month'   => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_quarter' => [$now->copy()->firstOfQuarter(), $now->copy()->endOfDay()],
            'this_year'    => [$now->copy()->startOfYear(),     $now->copy()->endOfDay()],
            default        => [$now->copy()->startOfMonth(),    $now->copy()->endOfDay()],
        };
    }

    /** Top KPI strip stats. */
    private function kpis(): array
    {
        $total    = Order::query()->count();
        $paid     = Order::query()->where('status', 'paid')->count();
        $pending  = Order::query()->where('status', 'pending')->count();
        $overdue  = Order::query()->where('status', 'pending')->where('created_at', '<', now()->subDays(15))->count();
        $outstandingAmt = (float) Order::query()->where('status', 'pending')->sum(DB::raw('COALESCE(total_amount, amount)'));

        $monthSum = (float) Order::query()->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfDay()])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));
        $prevMonthSum = (float) Order::query()->where('status', 'paid')
            ->whereBetween('paid_at', [now()->copy()->subMonth()->startOfMonth(), now()->copy()->subMonth()->endOfMonth()])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));
        $monthDelta = $prevMonthSum > 0 ? round((($monthSum - $prevMonthSum) / $prevMonthSum) * 100, 1) : ($monthSum > 0 ? 100.0 : 0);

        return [
            'total'        => number_format($total),
            'paid'         => number_format($paid),
            'paidPct'      => $total > 0 ? round($paid / $total * 100, 1) . '%' : '0%',
            'outstanding'  => number_format($pending),
            'outstandingAmt' => \App\Support\FormatSettings::symbol() . self::short($outstandingAmt) . ' due',
            'overdue'      => number_format($overdue),
            'monthSum'     => \App\Support\FormatSettings::symbol() . self::short($monthSum),
            'monthDelta'   => ($monthDelta >= 0 ? '+' : '') . $monthDelta . '% MoM',
            'monthDeltaPos'=> $monthDelta >= 0,
        ];
    }

    public static function short(float $n): string
    {
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
        if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'k';
        return number_format($n, 0);
    }

    /** Map order status → invoice status badge tone. */
    public static function badge(Order $o): array
    {
        return match ($o->status) {
            'paid'     => ['label' => 'paid',         'class' => 'bg-wa-mint text-wa-deep', 'dot' => 'bg-wa-green'],
            'pending'  => $o->created_at->lt(now()->subDays(15))
                            ? ['label' => 'overdue',  'class' => 'bg-accent-coral/10 text-accent-coral', 'dot' => 'bg-accent-coral']
                            : ['label' => 'outstanding','class' => 'bg-accent-amber/10 text-accent-amber', 'dot' => 'bg-accent-amber'],
            'refunded' => ['label' => 'refunded',     'class' => 'bg-[#F3E9FF] text-[#5B3D8A]', 'dot' => 'bg-[#9C7DB8]'],
            'failed'   => ['label' => 'void',         'class' => 'bg-paper-100 text-ink-700', 'dot' => 'bg-paper-300'],
            default    => ['label' => $o->status,     'class' => 'bg-paper-100 text-ink-700', 'dot' => 'bg-paper-300'],
        };
    }
}

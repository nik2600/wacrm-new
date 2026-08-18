@php
    $appName = brand_name();

    $cur = strtoupper($invoice->currency ?: 'USD');
    $sym =
        [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'AED' => 'AED ',
            'SGD' => 'S$',
            'ZAR' => 'R',
            'BRL' => 'R$',
        ][$cur] ??
        $cur . ' ';
    $money = fn($v) => $sym . number_format((float) $v, 2);

    $subtotal = (float) $invoice->amount;
    $discount = (float) ($invoice->discount_amount ?? 0);
    $taxRate = (float) ($invoice->tax_rate ?? 0);
    $taxAmt = (float) ($invoice->tax_amount ?? 0);
    $total = (float) ($invoice->total_amount ?: $subtotal - $discount + $taxAmt);
    $paidAmt = $invoice->status === 'paid' ? $total : 0.0;
    $balance = max(0, $total - $paidAmt);

    $badge = \App\Http\Controllers\Admin\InvoicesController::badge($invoice);
    $invNo = $invoice->order_number ?: 'INV-' . $invoice->id;
    $issue = $invoice->created_at;
    $paidAt = $invoice->paid_at;

    $billToName =
        $invoice->billing_company ?:
        ($invoice->customer_name ?:
        (optional($invoice->workspace)->name ?:
        optional($invoice->user)->name ?:
        '—'));
    $billToEmail = $invoice->customer_email ?: optional($invoice->user)->email;
    $billLines = array_values(
        array_filter([
            $invoice->billing_address,
            trim(implode(' ', array_filter([$invoice->billing_city, $invoice->billing_postal]))),
            $invoice->billing_country,
        ]),
    );

    $gatewayName = optional($invoice->gateway)->name ?: ucfirst((string) ($invoice->gateway_slug ?: 'manual'));
    $lineDesc = $package->name ?? __('Plan purchase');
@endphp
<x-layouts.admin :title="__('Invoice') . ' ' . $invNo" admin-key="invoices" page="invoices-view">
    <header
        class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30 no-print">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0 min-w-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/invoices') }}" class="hover:text-ink-900">{{ __('Invoices') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $invNo }}</span>
        </div>
        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $badge['class'] }} font-mono"><span
                    class="w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>{{ ucfirst($badge['label']) }}
                @if ($paidAt)
                    · {{ $paidAt->format('Y-m-d') }}
                @endif
            </span>
            <button onclick="window.print()"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="3" y="2" width="10" height="4" />
                    <rect x="2" y="6" width="12" height="6" rx="1" />
                    <rect x="4" y="9" width="8" height="5" />
                </svg>
                {{ __('Print') }}
            </button>
            <button onclick="window.print()"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                </svg>
                {{ __('Download PDF') }}
            </button>
        </div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7">

        <!-- Banner: status summary (admin-only, hidden in print) -->
        <div
            class="no-print mb-5 bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card flex flex-col sm:flex-row sm:items-center gap-4">
            <span class="w-12 h-12 rounded-xl bg-wa-bubble text-wa-deep grid place-items-center"><svg
                    viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 2h7l3 3v9H3z" />
                    <path d="M10 2v3h3" />
                    <path d="M5 8h6M5 11h4" />
                </svg></span>
            <div class="flex-1 min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Invoice') }}
                    #{{ $invNo }} · {{ $gatewayName }}@if ($invoice->gateway_payment_id)
                        · {{ $invoice->gateway_payment_id }}
                    @endif
                </div>
                <div class="font-serif text-[18px] leading-tight mt-0.5">{{ $billToName }} · {{ $lineDesc }}
                </div>
                <div class="text-[11.5px] text-ink-500 mt-1">{{ __('Issued') }}
                    {{ optional($issue)->format('Y-m-d') }}@if ($paidAt)
                        · {{ __('settled') }} {{ $paidAt->diffForHumans($issue, true) }}
                    @endif
                </div>
            </div>
            <div class="text-right">
                <div class="font-serif text-[26px] leading-none text-wa-deep">{{ $money($total) }}</div>
                <div class="text-[10.5px] text-ink-500 mt-1 font-mono">{{ $cur }} ·
                    {{ $invoice->status === 'paid' ? __('paid in full') : ucfirst($invoice->status) }}</div>
            </div>
        </div>

        <!-- ===== INVOICE DOCUMENT (this is what prints) ===== -->
        <div
            class="invoice-doc max-w-[860px] mx-auto bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">

            <!-- Doc header -->
            <div class="px-5 sm:px-8 lg:px-10 pt-10 pb-6 border-b border-paper-200">
                <div class="flex flex-col sm:flex-row items-start justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span
                                class="relative inline-flex items-center justify-center w-10 h-10 rounded-lg bg-wa-deep text-paper-0">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor">
                                    <path
                                        d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Zm5.07 14.07c-.21.6-1.22 1.14-1.7 1.21-.45.07-1.02.1-1.65-.1-.38-.12-.87-.28-1.49-.55-2.62-1.13-4.33-3.77-4.46-3.94-.13-.18-1.07-1.42-1.07-2.71 0-1.29.68-1.92.92-2.18.24-.27.52-.34.7-.34h.5c.16 0 .38-.06.59.45.21.51.71 1.76.77 1.89.06.13.1.28.02.45-.08.18-.12.28-.24.43-.12.15-.26.34-.37.46-.12.12-.25.26-.11.51.14.26.62 1.02 1.33 1.65.91.81 1.68 1.06 1.94 1.18.26.13.41.11.56-.06.15-.18.65-.76.83-1.02.18-.26.36-.21.6-.13.24.09 1.55.73 1.81.86.27.13.45.2.51.31.07.12.07.69-.14 1.29Z" />
                                </svg>
                            </span>
                            <span class="font-serif text-[26px] leading-none">{{ $billing['company'] }}</span>
                        </div>
                        <div class="mt-3 text-[11px] text-ink-600 leading-relaxed">
                            @if ($billing['address'])
                                {!! nl2br(e($billing['address'])) !!}<br>
                            @endif
                            @if ($billing['tax_id'])
                                GSTIN/VAT <span class="font-mono">{{ $billing['tax_id'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                            {{ __('Tax invoice') }}</div>
                        <div class="font-serif text-[36px] leading-none mt-1">{{ __('INVOICE') }}</div>
                        <div class="mt-3 grid grid-cols-[auto_auto] gap-x-3 gap-y-1 text-[11.5px] text-right">
                            <div class="text-ink-500">{{ __('Invoice #') }}</div>
                            <div class="font-mono font-semibold">{{ $invNo }}</div>
                            <div class="text-ink-500">{{ __('Issue date') }}</div>
                            <div class="font-mono">{{ optional($issue)->format('d M Y') }}</div>
                            @if ($paidAt)
                                <div class="text-ink-500">{{ __('Paid date') }}</div>
                                <div class="font-mono">{{ $paidAt->format('d M Y') }}</div>
                            @endif
                            <div class="text-ink-500">{{ __('Status') }}</div>
                            <div><span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $badge['class'] }} text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>{{ ucfirst($badge['label']) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bill to / Payment -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 px-5 sm:px-8 lg:px-10 py-6 border-b border-paper-200">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Bill to') }}</div>
                    <div class="font-semibold text-[14px]">{{ $billToName }}</div>
                    <div class="text-[11.5px] text-ink-700 leading-relaxed mt-1">
                        @if ($billToEmail)
                            {{ $billToEmail }}<br>
                        @endif
                        @foreach ($billLines as $bl)
                            {{ $bl }}<br>
                        @endforeach
                        @if ($invoice->billing_tax_id)
                            GSTIN/VAT <span class="font-mono">{{ $invoice->billing_tax_id }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Payment') }}</div>
                    <div class="text-[11.5px] text-ink-700 leading-relaxed">
                        <div class="grid grid-cols-[auto_auto] gap-x-3 gap-y-1">
                            <div class="text-ink-500">{{ __('Method') }}</div>
                            <div>{{ $gatewayName }}</div>
                            @if ($invoice->gateway_payment_id)
                                <div class="text-ink-500">{{ __('Txn ID') }}</div>
                                <div class="font-mono">{{ $invoice->gateway_payment_id }}</div>
                            @endif
                            @if ($paidAt)
                                <div class="text-ink-500">{{ __('Captured at') }}</div>
                                <div class="font-mono">{{ $paidAt->format('d M Y, H:i') }}</div>
                            @endif
                            <div class="text-ink-500">{{ __('Currency') }}</div>
                            <div>{{ $cur }}</div>
                            @if ($invoice->coupon_code)
                                <div class="text-ink-500">{{ __('Coupon') }}</div>
                                <div class="font-mono">{{ $invoice->coupon_code }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line items -->
            <div class="px-5 sm:px-8 lg:px-10 py-6">
                <div class="overflow-x-auto">
                <table class="w-full text-[12px] min-w-[480px]">
                    <thead>
                        <tr class="border-b border-paper-200">
                            <th
                                class="text-left font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 py-2.5">
                                {{ __('Description') }}</th>
                            <th
                                class="text-right font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 py-2.5 w-[80px]">
                                {{ __('Qty') }}</th>
                            <th
                                class="text-right font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 py-2.5 w-[120px]">
                                {{ __('Unit') }}</th>
                            <th
                                class="text-right font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 py-2.5 w-[120px]">
                                {{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr>
                            <td class="py-3">
                                <div class="font-semibold">{{ $lineDesc }}</div>
                                <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('Order') }}
                                    {{ $invNo }}@if ($package && $package->id)
                                        · plan_{{ $package->id }}
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 text-right font-mono">1</td>
                            <td class="py-3 text-right font-mono">{{ $money($subtotal) }}</td>
                            <td class="py-3 text-right font-mono">{{ $money($subtotal) }}</td>
                        </tr>
                        @if ($discount > 0)
                            <tr>
                                <td class="py-3">
                                    <div class="font-semibold">{{ __('Discount') }}</div>
                                    @if ($invoice->coupon_code)
                                        <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('code') }} <span
                                                class="font-mono">{{ $invoice->coupon_code }}</span></div>
                                    @endif
                                </td>
                                <td class="py-3 text-right font-mono">1</td>
                                <td class="py-3 text-right font-mono text-accent-coral">-{{ $money($discount) }}</td>
                                <td class="py-3 text-right font-mono text-accent-coral">-{{ $money($discount) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                </div>

                <!-- Totals -->
                <div class="flex justify-end mt-5">
                    <div class="w-full max-w-[300px] space-y-1.5 text-[12.5px]">
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Subtotal') }}</span><span
                                class="font-mono">{{ $money($subtotal) }}</span></div>
                        @if ($discount > 0)
                            <div class="flex items-center justify-between"><span
                                    class="text-ink-500">{{ __('Discount') }}</span><span
                                    class="font-mono text-accent-coral">-{{ $money($discount) }}</span></div>
                        @endif
                        @if ($taxAmt > 0 || $taxRate > 0)
                            <div class="flex items-center justify-between"><span
                                    class="text-ink-500">{{ $billing['tax_label'] }}
                                    ({{ rtrim(rtrim(number_format($taxRate, 2), '0'), '.') }}%)</span><span
                                    class="font-mono">{{ $money($taxAmt) }}</span></div>
                        @endif
                        <div class="flex items-center justify-between pt-2 mt-1 border-t border-paper-200">
                            <span class="font-semibold">{{ __('Total') }}</span><span
                                class="font-mono font-semibold text-[16px]">{{ $money($total) }}</span>
                        </div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Amount paid') }}</span><span
                                class="font-mono text-wa-deep">{{ $money($paidAmt) }}</span></div>
                        <div class="flex items-center justify-between pt-2 mt-1 border-t border-paper-200">
                            <span class="font-semibold">{{ __('Balance due') }}</span><span
                                class="font-mono font-semibold text-[16px] {{ $balance > 0 ? 'text-accent-coral' : 'text-wa-deep' }}">{{ $money($balance) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer / notes -->
            <div class="border-t border-paper-200 px-5 sm:px-8 lg:px-10 py-6 bg-paper-50/40">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Notes') }}</div>
                        <p class="text-[11.5px] text-ink-700 leading-relaxed">
                            {{ __('Thank you for your business. This invoice covers the order referenced above. Reference the invoice number for any billing queries.') }}
                        </p>
                    </div>
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Need help?') }}</div>
                        <p class="text-[11.5px] text-ink-700 leading-relaxed">
                            @if ($billing['email'])
                                {{ __('Email') }} <a href="mailto:{{ $billing['email'] }}"
                                    class="text-wa-deep">{{ $billing['email'] }}</a>
                            @endif
                            @if ($billing['phone'])
                                · {{ __('Phone') }} {{ $billing['phone'] }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="mt-5 pt-4 border-t border-paper-200 text-center text-[10.5px] text-ink-500 font-mono">
                    {{ $billing['company'] }}@if ($billing['reg_no'])
                        · {{ $billing['reg_no'] }}
                    @endif ·
                    {{ __('This is a computer-generated invoice and does not require a signature.') }}
                </div>
            </div>
        </div>

    </main>

</x-layouts.admin>

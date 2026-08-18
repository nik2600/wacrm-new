<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Invoice {{ $order->order_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f0;
            color: #0B1F1C;
            margin: 0;
            padding: 30px 20px;
        }

        .invoice {
            max-width: 780px;
            margin: 0 auto;
            background: #fff;
            padding: 48px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border-radius: 8px;
        }

        .hd {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #E5DFD0;
        }

        .hd .brand h1 {
            margin: 0 0 6px;
            font-size: 24px;
        }

        .hd .brand .meta {
            font-size: 11px;
            color: #6B807C;
            line-height: 1.5;
        }

        .hd .invno {
            text-align: right;
        }

        .hd .invno .lbl {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: #6B807C;
        }

        .hd .invno .num {
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px;
            margin-top: 4px;
            color: #075E54;
        }

        .hd .invno .dt {
            font-size: 11px;
            color: #6B807C;
            margin-top: 6px;
            font-family: 'JetBrains Mono', monospace;
        }

        .bill {
            display: flex;
            gap: 24px;
            margin-bottom: 32px;
        }

        .bill .col {
            flex: 1;
        }

        .bill .col .lbl {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: #6B807C;
            margin-bottom: 6px;
        }

        .bill .col .name {
            font-weight: 600;
            font-size: 13px;
        }

        .bill .col .row {
            font-size: 12px;
            color: #3A5A55;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #6B807C;
            padding: 10px 8px;
            border-bottom: 1px solid #E5DFD0;
            background: #fafaf6;
        }

        td {
            padding: 14px 8px;
            font-size: 13px;
            border-bottom: 1px solid #efebe0;
        }

        td.amt {
            text-align: right;
            font-family: 'JetBrains Mono', monospace;
        }

        .totals {
            margin-left: auto;
            width: 300px;
        }

        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }

        .totals .row .lbl {
            color: #3A5A55;
        }

        .totals .row .val {
            font-family: 'JetBrains Mono', monospace;
        }

        .totals .row.total {
            border-top: 1px solid #E5DFD0;
            padding-top: 12px;
            margin-top: 6px;
            font-size: 18px;
            font-weight: 600;
        }

        .totals .row.total .val {
            color: #075E54;
        }

        .totals .row.discount .val,
        .totals .row.discount .lbl {
            color: #075E54;
        }

        .status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .status.paid {
            background: #D6EFE0;
            color: #075E54;
        }

        .status.pending {
            background: #FFF4E0;
            color: #7B5A14;
        }

        .status.failed {
            background: #FCE0DA;
            color: #A1431F;
        }

        .status.refunded {
            background: #FCE0DA;
            color: #A1431F;
        }

        .ft {
            margin-top: 36px;
            padding-top: 18px;
            border-top: 1px solid #E5DFD0;
            font-size: 11px;
            color: #6B807C;
            line-height: 1.6;
        }

        .ft .row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
        }

        .ft .ref {
            font-family: 'JetBrains Mono', monospace;
        }

        .actions {
            max-width: 780px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .actions a {
            color: #075E54;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .actions a:hover {
            text-decoration: underline;
        }

        .actions button {
            background: #075E54;
            color: #fff;
            border: 0;
            padding: 10px 22px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .actions button:hover {
            background: #128C7E;
        }

        @media (max-width: 640px) {
            body {
                padding: 16px 12px;
            }

            .invoice {
                padding: 24px 20px;
            }

            .hd {
                flex-direction: column;
                gap: 16px;
            }

            .hd .invno {
                text-align: left;
            }

            .bill {
                flex-direction: column;
                gap: 16px;
            }

            .totals {
                width: 100%;
                margin-left: 0;
            }

            .ft .row {
                flex-direction: column;
                gap: 8px;
            }

            .actions {
                flex-wrap: wrap;
                gap: 12px;
            }
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .actions {
                display: none;
            }

            .invoice {
                box-shadow: none;
                padding: 24px;
                border-radius: 0;
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="actions">
        <a href="{{ url('/account?tab=orders') }}">← Back to orders</a>
        <button onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
    </div>

    <div class="invoice">

        <div class="hd">
            <div class="brand">
                @if (!empty($brand['logo']))
                    {{-- The logo already carries the brand name — don't repeat it as text. --}}
                    <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}"
                        style="max-height:48px;max-width:220px;width:auto;object-fit:contain;display:block;margin-bottom:8px;">
                @else
                    <h1>{{ $brand['name'] }}</h1>
                @endif
                <div class="meta">
                    @if ($brand['address'])
                        <div>{{ $brand['address'] }}</div>
                    @endif
                    @if (!empty($brand['email']))
                        <div>{{ $brand['email'] }}</div>
                    @endif
                    @if (!empty($brand['phone']))
                        <div>{{ $brand['phone'] }}</div>
                    @endif
                    @if ($brand['tax_id'])
                        <div>{{ $brand['tax_label'] ?: 'Tax ID' }}: {{ $brand['tax_id'] }}</div>
                    @endif
                    @if (!empty($brand['reg_no']))
                        <div>{{ __('Reg. No:') }} {{ $brand['reg_no'] }}</div>
                    @endif
                </div>
            </div>
            <div class="invno">
                <div class="lbl">{{ __('Invoice') }}</div>
                <div class="num">{{ $order->order_number }}</div>
                <div class="dt">{{ optional($order->created_at)->format('M j, Y') }}</div>
                <div style="margin-top:10px"><span class="status {{ $order->status }}">{{ $order->status }}</span>
                </div>
            </div>
        </div>

        <div class="bill">
            <div class="col">
                <div class="lbl">{{ __('Billed to') }}</div>
                <div class="name">{{ $order->customer_name ?: optional($order->user)->name }}</div>
                <div class="row">{{ $order->customer_email ?: optional($order->user)->email }}</div>
                @if ($order->billing_company)
                    <div class="row">{{ $order->billing_company }}</div>
                @endif
                @if ($order->billing_address)
                    <div class="row">{{ $order->billing_address }}</div>
                @endif
                @if ($order->billing_city || $order->billing_postal)
                    <div class="row">{{ trim($order->billing_city . ' ' . $order->billing_postal) }}</div>
                @endif
                @if ($order->billing_country)
                    <div class="row">{{ $order->billing_country }}</div>
                @endif
                @if ($order->billing_tax_id)
                    <div class="row">{{ __('Tax ID:') }} <span
                            style="font-family:'JetBrains Mono', monospace">{{ $order->billing_tax_id }}</span></div>
                @endif
            </div>
            <div class="col">
                <div class="lbl">{{ __('Payment') }}</div>
                <div class="row">Method:
                    {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $order->gateway_slug ?: 'manual')) }}</div>
                @if ($order->gateway_payment_id)
                    <div class="row">{{ __('Reference:') }} <span class="ref"
                            style="font-family:'JetBrains Mono', monospace">{{ $order->gateway_payment_id }}</span>
                    </div>
                @endif
                @if ($order->paid_at)
                    <div class="row">Paid on: {{ $order->paid_at->format('M j, Y H:i') }}</div>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:60%">{{ __('Item') }}</th>
                    <th style="width:15%; text-align:right">{{ __('Qty') }}</th>
                    <th style="width:25%; text-align:right">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="font-weight:600">
                            {{ optional($order->package)->pname ?: 'Plan #' . $order->package_id }}</div>
                        @if (optional($order->package)->subtitle)
                            <div style="font-size:11px; color:#6B807C; margin-top:2px">{{ $order->package->subtitle }}
                            </div>
                        @endif
                        @if ($order->package)
                            <div style="font-size:11px; color:#6B807C; margin-top:2px">
                                {{ $order->package->plan_duration }} {{ $order->package->plan_unit }}
                            </div>
                        @endif
                    </td>
                    <td class="amt">1</td>
                    <td class="amt">{!! \App\Support\FormatSettings::formatIn(
                        $order->amount + $order->discount_amount - $order->tax_amount,
                        $order->currency,
                    ) !!}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="row">
                <span class="lbl">{{ __('Subtotal') }}</span>
                <span class="val">{!! \App\Support\FormatSettings::formatIn(
                    $order->amount + $order->discount_amount - $order->tax_amount,
                    $order->currency,
                ) !!}</span>
            </div>
            @if ($order->discount_amount > 0)
                <div class="row discount">
                    <span class="lbl">Discount {{ $order->coupon_code ? '· ' . $order->coupon_code : '' }}</span>
                    <span class="val">−{!! \App\Support\FormatSettings::formatIn($order->discount_amount, $order->currency) !!}</span>
                </div>
            @endif
            @if ($order->tax_amount > 0)
                <div class="row">
                    <span class="lbl">{{ \App\Models\SystemSetting::get('checkout.tax_label', 'Tax') }}
                        ({{ rtrim(rtrim(number_format($order->tax_rate, 2), '0'), '.') }}%)</span>
                    <span class="val">{!! \App\Support\FormatSettings::formatIn($order->tax_amount, $order->currency) !!}</span>
                </div>
            @endif
            <div class="row total">
                <span class="lbl">{{ __('Total') }}</span>
                <span class="val">{!! \App\Support\FormatSettings::formatIn($order->amount, $order->currency) !!}</span>
            </div>
        </div>

        <div class="ft">
            <div class="row">
                {{-- Support address = the platform's billing/support email (same
                     source as the company block above), NEVER $order->customer_email
                     (that's the BUYER's own address — telling them to email themselves). --}}
                <div>Thank you for your purchase. For support or refunds within
                    {{ (int) \App\Models\SystemSetting::get('pricing.refund_days', 7) }} days, reply to
                    {{ $brand['email'] ?: 'support@' . parse_url(config('app.url'), PHP_URL_HOST) }}.</div>
                <div class="ref">Order ID: {{ $order->id }}</div>
            </div>
        </div>

    </div>

</body>

</html>

@php
    use App\Models\WaProduct;
    $cur = $order->currency_code ?: 'INR';
    $money = fn($minor) => WaProduct::formatCurrency((int) $minor, $cur);
    $items = $order->renderable_items ?? ($order->items_json ?? []);
    $cancelled = $order->status === 'cancelled';
    $steps = ['new' => 'Placed', 'confirmed' => 'Confirmed', 'paid' => 'Paid', 'shipped' => 'Shipped'];
    $order_idx = array_search($order->status, array_keys($steps), true);
    if ($order_idx === false) {
        $order_idx = 0;
    }
    $brand = $brand ?? '#075E54';
    $shopName = $shopName ?? 'Store';
@endphp
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Order') }} #{{ $order->id }} · {{ $shopName }}</title>
    <style>
        :root {
            --brand: {{ $brand }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f4f6f5;
            color: #1d2b27;
        }

        .wrap {
            max-width: 560px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }

        .card {
            background: #fff;
            border: 1px solid #e4ebe8;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .brandbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        .brandbar .logo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            object-fit: cover;
            background: var(--brand);
            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 700;
        }

        .h1 {
            font-size: 19px;
            font-weight: 700;
            margin: 0;
        }

        .muted {
            color: #6b807c;
            font-size: 13px;
        }

        .pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .steps {
            display: flex;
            gap: 0;
            margin: 6px 0 4px;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step .dot {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            margin: 0 auto 6px;
            background: #d7e0dd;
            display: grid;
            place-items: center;
        }

        .step.done .dot {
            background: var(--brand);
        }

        .step .dot svg {
            width: 12px;
            height: 12px;
            stroke: #fff;
        }

        .step .lbl {
            font-size: 11px;
            color: #6b807c;
        }

        .step.done .lbl {
            color: #1d2b27;
            font-weight: 600;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 11px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #d7e0dd;
            z-index: 0;
        }

        .step.done:not(:last-child)::after {
            background: var(--brand);
        }

        .step .dot {
            position: relative;
            z-index: 1;
        }

        .row {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f4f2;
        }

        .row:last-child {
            border-bottom: 0;
        }

        .row img,
        .row .ph {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            background: #f0f4f2;
            flex: none;
            display: grid;
            place-items: center;
        }

        .row .nm {
            font-size: 13.5px;
            font-weight: 600;
        }

        .row .qty {
            font-size: 12px;
            color: #6b807c;
        }

        .row .amt {
            margin-left: auto;
            font-weight: 600;
            font-size: 13.5px;
            white-space: nowrap;
        }

        .tot {
            display: flex;
            justify-content: space-between;
            font-size: 13.5px;
            padding: 4px 0;
        }

        .tot.grand {
            font-size: 16px;
            font-weight: 700;
            border-top: 1px solid #e4ebe8;
            margin-top: 6px;
            padding-top: 10px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            background: var(--brand);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .btn svg {
            width: 16px;
            height: 16px;
        }

        .lbl-k {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6b807c;
            margin-bottom: 2px;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="brandbar">
            @if (!empty($logo))
            <img class="logo" src="{{ $logo }}" alt="">@else<span
                    class="logo">{{ strtoupper(substr($shopName, 0, 1)) }}</span>
            @endif
            <div>
                <div class="muted">{{ $shopName }}</div>
                <h1 class="h1">{{ __('Order') }} #{{ $order->id }}</h1>
            </div>
            <span class="pill"
                style="margin-left:auto;{{ $cancelled ? 'background:#fbe4dd;color:#A1431F' : 'background:#d7f0e6;color:#075E54' }}">
                {{ ucfirst($order->status) }}
            </span>
        </div>

        <div class="card">
            @if ($cancelled)
                <div class="muted" style="text-align:center;padding:8px 0">
                    {{ __('This order was cancelled. Contact us if that\'s unexpected.') }}</div>
            @else
                <div class="steps">
                    @foreach ($steps as $key => $label)
                        <div class="step {{ $loop->index <= $order_idx ? 'done' : '' }}">
                            <div class="dot">
                                @if ($loop->index <= $order_idx)
                                    <svg viewBox="0 0 16 16" fill="none" stroke-width="2.4">
                                        <path d="m3 8 3 3 7-7" />
                                    </svg>
                                @endif
                            </div>
                            <div class="lbl">{{ __($label) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card">
            @foreach ($items as $i)
                @php
                    $qty = (int) ($i['qty'] ?? 1);
                    $pm = (int) ($i['price_minor'] ?? 0);
                @endphp
                <div class="row">
                    @if (!empty($i['image']))
                    <img src="{{ $i['image'] }}" alt="">@else<span class="ph"><svg
                                viewBox="0 0 16 16" width="20" height="20" fill="none" stroke="#9bb0aa"
                                stroke-width="1.4">
                                <path d="M2 5l6-3 6 3v6l-6 3-6-3z" />
                                <path d="M2 5l6 3 6-3M8 8v6" />
                            </svg></span>
                    @endif
                    <div>
                        <div class="nm">{{ $i['name'] ?? '—' }}</div>
                        <div class="qty">{{ __('Qty') }} {{ $qty }} · {{ $money($pm) }}</div>
                    </div>
                    <div class="amt">{{ $money($pm * $qty) }}</div>
                </div>
            @endforeach

            @php
                $shipping = (int) ($order->shipping_minor ?? 0);
                $sub = (int) $order->total_minor - $shipping;
            @endphp
            <div style="margin-top:12px">
                <div class="tot"><span class="muted">{{ __('Subtotal') }}</span><span>{{ $money($sub) }}</span>
                </div>
                <div class="tot"><span
                        class="muted">{{ __('Shipping') }}</span><span>{{ $shipping > 0 ? $money($shipping) : __('Free') }}</span>
                </div>
                <div class="tot grand"><span>{{ __('Total') }}</span><span>{{ $order->total_display }}</span></div>
            </div>
        </div>

        @if ($order->customer_address)
            <div class="card">
                <div class="lbl-k">{{ __('Delivery to') }}</div>
                <div style="font-size:13.5px">{{ $order->customer_name }}</div>
                <div class="muted" style="white-space:pre-line">{{ $order->customer_address }}</div>
            </div>
        @endif

        @if (!empty($order->payment_link) && $order->status !== 'paid')
            <a class="btn" style="margin-bottom:12px" href="{{ $order->payment_link }}">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7">
                    <rect x="2" y="3.5" width="12" height="9" rx="1.5" />
                    <path d="M2 6.5h12" />
                </svg>
                {{ __('Pay now') }}
            </a>
        @endif

        @if (!empty($waNumber))
            <a class="btn"
                href="https://wa.me/{{ preg_replace('/\D+/', '', $waNumber) }}?text={{ rawurlencode('Hi! About my order #' . $order->id) }}">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path
                        d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2zm0 2a8 8 0 1 1-4.2 14.8l-.3-.2-2.8.8.8-2.7-.2-.3A8 8 0 0 1 12 4z" />
                </svg>
                {{ __('Message us about this order') }}
            </a>
        @endif

        <p class="muted" style="text-align:center;margin-top:20px">
            {{ $footer ?? '© ' . date('Y') . ' ' . $shopName }}</p>
    </div>
</body>

</html>

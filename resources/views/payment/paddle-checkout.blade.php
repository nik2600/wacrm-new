<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Complete your payment') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f4f6f5; color: #0b1f1c; padding: 24px;
        }
        .card {
            width: 100%; max-width: 420px; background: #fff; border: 1px solid #e5e9e7;
            border-radius: 16px; padding: 32px 28px; text-align: center;
            box-shadow: 0 20px 60px -30px rgba(11,31,28,0.35);
        }
        .spin { width: 40px; height: 40px; margin: 4px auto 18px; color: #0b7a5b; }
        .spin svg { width: 100%; height: 100%; animation: rot 0.9s linear infinite; }
        @keyframes rot { to { transform: rotate(360deg); } }
        h1 { font-size: 18px; margin: 0 0 6px; font-weight: 600; }
        p  { font-size: 13.5px; line-height: 1.5; color: #4b5854; margin: 0 0 6px; }
        .ord { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px; color: #8a938f; margin-top: 10px; }
        .btn {
            display: inline-block; margin-top: 18px; padding: 11px 20px; border-radius: 999px;
            background: #0b7a5b; color: #fff; font-size: 13.5px; font-weight: 600; text-decoration: none; border: 0; cursor: pointer;
        }
        .btn:hover { background: #0a6b50; }
        .muted { display: inline-block; margin-top: 14px; font-size: 12.5px; color: #8a938f; text-decoration: none; }
        .muted:hover { color: #4b5854; }
        #err { display: none; margin-top: 14px; font-size: 12.5px; color: #b91c1c; line-height: 1.45; }
    </style>
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
</head>
<body>
    <div class="card">
        <div class="spin" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                <path d="M12 3a9 9 0 1 0 9 9" />
            </svg>
        </div>
        <h1>{{ __('Opening secure checkout…') }}</h1>
        <p>{{ __('The Paddle payment window should appear in a moment. Please complete your payment there.') }}</p>
        <div id="err"></div>
        <button type="button" id="retry" class="btn">{{ __('Open payment window') }}</button>
        <div><a href="{{ $cancelUrl }}" class="muted">{{ __('Cancel and go back') }}</a></div>
        <div class="ord">{{ __('Order') }} {{ $orderNumber }}</div>
    </div>

    <script>
        (function () {
            var TXN     = @json($txnId);
            var TOKEN   = @json($clientToken);
            var ENV     = @json($environment);
            var SUCCESS = @json($successUrl);

            function showError(msg) {
                var el = document.getElementById('err');
                el.textContent = msg;
                el.style.display = 'block';
            }

            var opened = false;
            function openCheckout() {
                if (!window.Paddle) { showError('{{ __('Payment library not loaded yet — please try again.') }}'); return; }
                try {
                    if (ENV) { window.Paddle.Environment.set(ENV); }
                    if (!opened) {
                        window.Paddle.Initialize({
                            token: TOKEN,
                            eventCallback: function (ev) {
                                if (ev && ev.name === 'checkout.completed') { window.location.href = SUCCESS; }
                            }
                        });
                    }
                    opened = true;
                    window.Paddle.Checkout.open({
                        transactionId: TXN,
                        settings: { displayMode: 'overlay', successUrl: SUCCESS }
                    });
                } catch (e) {
                    showError('{{ __('Could not open Paddle checkout:') }} ' + (e && e.message ? e.message : e));
                }
            }

            // Paddle.js is async — wait for it (up to ~6s), then open automatically.
            function waitForPaddle(tries) {
                if (window.Paddle) { openCheckout(); return; }
                if (tries > 60) { showError('{{ __('Paddle could not load. Check your connection and use the button below.') }}'); return; }
                setTimeout(function () { waitForPaddle(tries + 1); }, 100);
            }
            waitForPaddle(0);

            document.getElementById('retry').addEventListener('click', function (e) {
                e.preventDefault();
                openCheckout();
            });
        })();
    </script>
</body>
</html>

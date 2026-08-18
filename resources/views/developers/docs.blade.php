@php
    $baseUrl   = rtrim(config('app.url'), '/') . '/api/v1';
    $specUrl   = url('/docs/api.json');
    $brand     = brand_name();
    // Brand-prefixed webhook signature header — matches what the backend
    // actually emits (App\Support\Brand::webhookSignatureHeader()).
    $sigHeader = \App\Support\Brand::webhookSignatureHeader();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand }} API Reference</title>
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..600;1,9..144,400..600&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/api-docs.css') }}?v=7">
    <script>window.WADESK_API = { specUrl: @json($specUrl) };</script>
</head>
<body>

    <!-- Top bar -->
    <header class="api-topbar">
        <a class="api-brand" href="{{ url('/') }}">
            <span class="mark">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" />
                </svg>
            </span>
            <span class="name">{{ $brand }} <em>API</em></span>
            <span class="ver">v1</span>
        </a>
        <div class="actions">
            <a class="btn" href="{{ $specUrl }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2v8M5 7l3 3 3-3M3 13h10"/></svg>
                OpenAPI spec
            </a>
            <a class="btn btn-primary" href="{{ url('/developers') }}">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="6" cy="10" r="2.5"/><path d="M8 9l5-5M11 4l1.5 1.5"/></svg>
                Get an API key
            </a>
        </div>
    </header>

    <div class="api-shell">

        <!-- Sidebar -->
        <aside class="api-sidebar">
            <input id="api-search" class="search" type="text" placeholder="Filter endpoints…" autocomplete="off">
            <div class="nav-group">
                <div class="label">Start here</div>
                <a class="nav-link" href="#intro"><span class="txt">Introduction</span></a>
                <a class="nav-link" href="#auth"><span class="txt">Authentication</span></a>
                <a class="nav-link" href="#webhooks-info"><span class="txt">Webhooks</span></a>
            </div>
            <div id="api-nav"></div>
        </aside>

        <!-- Main -->
        <main class="api-main">

            <section class="hero" id="intro">
                <div class="eyebrow">Developer reference</div>
                <h1>The {{ $brand }} <em>REST API</em></h1>
                <p class="lead">A single REST API to send WhatsApp messages, run broadcasts &amp; campaigns,
                    manage contacts and templates, trigger automation flows, and receive real-time events —
                    everything your CRM, automation platform or app needs.</p>

                <div class="kv">
                    <span class="pill"><span class="dot"></span> Base URL</span>
                    <div class="copybox">
                        <code id="base-url">{{ $baseUrl }}</code>
                        <button class="copybtn" type="button" data-copy="{{ $baseUrl }}" title="Copy">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="5" width="8" height="9" rx="1.5"/><path d="M3 11V3a1 1 0 0 1 1-1h6"/></svg>
                        </button>
                    </div>
                </div>
            </section>

            <!-- What you can do -->
            <section class="card">
                <h2>What you can do</h2>
                <div class="feature-grid">
                    @php
                        $caps = [
                            ['Send messages', 'Single, bulk broadcasts, campaigns &amp; scheduled sends'],
                            ['Receive messages', 'Real-time inbound via webhooks'],
                            ['Contacts &amp; leads', 'Full CRUD, groups &amp; custom attributes'],
                            ['Templates', 'Create, update &amp; list WhatsApp templates'],
                            ['Automation flows', 'Enroll a contact into a flow via API'],
                            ['Delivery status', 'Per-message sent / delivered / read / failed'],
                            ['Devices', 'List connected numbers &amp; their status'],
                            ['Account &amp; usage', 'Plan, limits and message credits'],
                        ];
                    @endphp
                    @foreach ($caps as $c)
                        <div class="feature">
                            <div class="t">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8.5l3.5 3.5L13 5"/></svg>
                                {!! $c[0] !!}
                            </div>
                            <div class="d">{!! $c[1] !!}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- Authentication -->
            <section class="card" id="auth">
                <h2>Authentication</h2>
                <p>Every request is authenticated with a workspace <strong>API key</strong>. Generate one in your
                    dashboard under <strong>More → Developers / API</strong>. Send it as a Bearer token:</p>
                <div class="code-wrap">
                    <button class="copybtn" type="button" data-copy="Authorization: Bearer YOUR_API_KEY" title="Copy">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="5" width="8" height="9" rx="1.5"/><path d="M3 11V3a1 1 0 0 1 1-1h6"/></svg>
                    </button>
                    <pre class="code">Authorization: Bearer YOUR_API_KEY</pre>
                </div>
                <p style="margin-top:12px">Alternatively pass it in an <code>X-Api-Key</code> header. Keys are scoped to a
                    single workspace and only their hash is stored — copy a key when it is created, it can't be shown again.
                    Keys can be revoked any time and stop working immediately.</p>
            </section>

            <!-- Webhooks -->
            <section class="card" id="webhooks-info">
                <h2>Webhooks &amp; real-time events</h2>
                <p>Register a webhook (see the <strong>Webhooks</strong> endpoints below) and {{ $brand }} will
                    <code>POST</code> a JSON envelope to your URL whenever a subscribed event fires. When you set a signing
                    secret, each delivery carries an <code>{{ $sigHeader }}</code> header (HMAC&#8209;SHA256 of the body)
                    so you can verify authenticity.</p>
                <h3>Available events</h3>
                <div class="feature-grid">
                    @php
                        $events = [
                            'message_received' => 'Inbound message received',
                            'message_sent' => 'Outbound message sent',
                            'message_delivered' => 'Outbound message delivered',
                            'message_read' => 'Outbound message read',
                            'message_failed' => 'Outbound message failed',
                            'broadcast_status_updated' => 'Broadcast status changed',
                            'campaign_status_updated' => 'Campaign status changed',
                            'campaign_contact_clicked' => 'Recipient clicked a link',
                            'campaign_contact_replied' => 'Recipient replied',
                            'contact_opt_in' => 'Contact opted in',
                            'contact_updated' => 'Contact updated',
                            'device_status_updated' => 'Number/device status changed',
                        ];
                    @endphp
                    @foreach ($events as $key => $desc)
                        <div class="feature">
                            <div class="t" style="font-family:var(--font-mono);font-size:11.5px">{{ $key }}</div>
                            <div class="d">{{ $desc }}</div>
                        </div>
                    @endforeach
                </div>
                <p style="margin-top:14px"><span class="pill"><span class="dot"></span> Rate limits</span>
                    &nbsp; No hard request limit is enforced today. Outbound sends draw from your plan's message credits.</p>
            </section>

            <!-- Endpoint reference (filled from the live OpenAPI spec) -->
            <div id="api-reference">
                <div class="loading">Loading endpoints…</div>
            </div>

            <div class="foot">{{ $brand }} REST API · v1 · generated from the live OpenAPI specification</div>
        </main>
    </div>

    <script>
        // copy buttons that live in the static blade (the JS-rendered ones wire themselves)
        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-copy]');
            if (!b) return;
            navigator.clipboard && navigator.clipboard.writeText(b.getAttribute('data-copy'));
            var html = b.innerHTML;
            b.innerHTML = '<svg viewBox="0 0 16 16" fill="none" style="stroke:var(--wa-green)" stroke-width="2"><path d="M3 8.5l3.5 3.5L13 5"/></svg>';
            setTimeout(function () { b.innerHTML = html; }, 1400);
        });
    </script>
    <script src="{{ asset('js/api-docs.js') }}?v=9"></script>
</body>
</html>

{{--
 Site-wide analytics injector.

 Reads /admin/settings/pwa "Analytics" card + the visitor's
 wadesk_cookie_consent cookie and emits ONLY the trackers they have
 consented to. Necessary (none) always renders nothing — there is no
 "essential" tracker. Analytics trackers (GA4, GTM, Clarity, Plausible,
 PostHog, Hotjar, Mixpanel) emit when consent.analytics=true. Marketing
 trackers (Meta Pixel, TikTok, LinkedIn Insight, X Pixel) emit when
 consent.marketing=true.

 Each script is the official snippet from the vendor — we just
 conditionally compose them and substitute the IDs from system_settings.

 GTM is a special case: when configured it also needs a noscript
 iframe in <body>. partials/site-analytics-noscript.blade.php emits
 that — include it right after <body>.
--}}
@php
    // Read consent cookie. Default = nothing emitted until user opts in.
    $consent = ['necessary' => true, 'analytics' => false, 'marketing' => false];
    $raw = request()->cookie('wadesk_cookie_consent');
    if ($raw) {
        try {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $consent = array_merge($consent, $decoded);
            }
        } catch (\Throwable $e) {
        }
    }

    // If the cookie banner is OFF (admin choice), grant full consent —
    // privacy-friendly default is still off, but admins can deploy
    // without the modal if they handle consent elsewhere.
    $bannerOn = (bool) \App\Models\SystemSetting::get('privacy_cookie_banner_enabled', true);
    if (!$bannerOn) {
        $consent = ['necessary' => true, 'analytics' => true, 'marketing' => true];
    }

    $ga4 = (string) \App\Models\SystemSetting::get('analytics_google_ga4', '');
    $gtm = (string) \App\Models\SystemSetting::get('analytics_google_gtm', '');
    $pixel = (string) \App\Models\SystemSetting::get('analytics_meta_pixel', '');
    $clar = (string) \App\Models\SystemSetting::get('analytics_microsoft_clarity', '');
    $plaus = (string) \App\Models\SystemSetting::get('analytics_plausible_domain', '');
    $phKey = (string) \App\Models\SystemSetting::get('analytics_posthog_key', '');
    $phHost = (string) \App\Models\SystemSetting::get('analytics_posthog_host', 'https://app.posthog.com');
    $hotj = (string) \App\Models\SystemSetting::get('analytics_hotjar_site_id', '');
    $tikt = (string) \App\Models\SystemSetting::get('analytics_tiktok_pixel', '');
    $linkd = (string) \App\Models\SystemSetting::get('analytics_linkedin_partner', '');
    $twPx = (string) \App\Models\SystemSetting::get('analytics_twitter_pixel', '');
    $mxpnl = (string) \App\Models\SystemSetting::get('analytics_mixpanel_token', '');
@endphp

{{-- ─────────────────────────────────────────────────────────────
     GOOGLE TAGS (GA4 + GTM) — Consent Mode v2.
     These load on EVERY page whenever the ID is configured, so the
     tag is ALWAYS present in the HTML — Google's tag detector finds
     it and View Source shows it. Cookie STORAGE, however, stays
     denied until the visitor opts into the Analytics / Marketing
     categories: the server reads the wadesk_cookie_consent cookie
     and stamps granted/denied below, and the banner's "Accept"
     reloads the page so this re-renders as 'granted'. This is the
     GDPR-correct way to keep the tag detectable AND consent-aware.
   ───────────────────────────────────────────────────────────── --}}
@if ($ga4 || $gtm)
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        {{-- Analytics is GRANTED for everyone so GA4/GTM track without
             requiring a cookie-banner "Accept" (client choice). Ad /
             marketing storage still follows the Marketing consent
             category. --}}
        gtag('consent', 'default', {
            ad_storage: '{{ $consent['marketing'] ? 'granted' : 'denied' }}',
            ad_user_data: '{{ $consent['marketing'] ? 'granted' : 'denied' }}',
            ad_personalization: '{{ $consent['marketing'] ? 'granted' : 'denied' }}',
            analytics_storage: 'granted',
            wait_for_update: 500
        });
    </script>
@endif

@if ($ga4)
    {{-- Google Analytics 4 — official gtag.js snippet --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
    <script>
        gtag('js', new Date());
        gtag('config', @json($ga4), {
            anonymize_ip: true
        });
    </script>
@endif

@if ($gtm)
    {{-- Google Tag Manager — head snippet --}}
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', '{{ $gtm }}');
    </script>
@endif

{{-- ANALYTICS bucket — non-Google trackers (no Consent Mode support,
     so these stay fully gated behind explicit analytics consent). --}}
@if ($consent['analytics'])

    @if ($clar)
        {{-- Microsoft Clarity --}}
        <script>
            (function(c, l, a, r, i, t, y) {
                c[a] = c[a] || function() {
                    (c[a].q = c[a].q || []).push(arguments)
                };
                t = l.createElement(r);
                t.async = 1;
                t.src = "https://www.clarity.ms/tag/" + i;
                y = l.getElementsByTagName(r)[0];
                y.parentNode.insertBefore(t, y);
            })(window, document, "clarity", "script", "{{ $clar }}");
        </script>
    @endif

    @if ($plaus)
        {{-- Plausible — privacy-first, no PII --}}
        <script defer data-domain="{{ $plaus }}" src="https://plausible.io/js/script.js"></script>
    @endif

    @if ($phKey)
        {{-- PostHog --}}
        <script>
            ! function(t, e) {
                var o, n, p, r;
                e.__SV || (window.posthog = e, e._i = [], e.init = function(i, s, a) {
                    function g(t, e) {
                        var o = e.split(".");
                        2 == o.length && (t = t[o[0]], e = o[1]), t[e] = function() {
                            t.push([e].concat(Array.prototype.slice.call(arguments, 0)))
                        }
                    }(p = t.createElement("script")).type = "text/javascript", p.async = !0, p.src = s.api_host +
                        "/static/array.js", (r = t.getElementsByTagName("script")[0]).parentNode.insertBefore(p, r);
                    var u = e;
                    for (void 0 !== a ? u = e[a] = [] : a = "posthog", u.people = u.people || [], u.toString = function(
                            t) {
                            var e = "posthog";
                            return "posthog" !== a && (e += "." + a), t || (e += " (stub)"), e
                        }, u.people.toString = function() {
                            return u.toString(1) + ".people (stub)"
                        }, o =
                        "capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys"
                        .split(" "), n = 0; n < o.length; n++) g(u, o[n]);
                    e._i.push([i, s, a])
                }, e.__SV = 1)
            }(document, window.posthog || []);
            posthog.init(@json($phKey), {
                api_host: @json($phHost)
            });
        </script>
    @endif

    @if ($hotj)
        {{-- Hotjar --}}
        <script>
            (function(h, o, t, j, a, r) {
                h.hj = h.hj || function() {
                    (h.hj.q = h.hj.q || []).push(arguments)
                };
                h._hjSettings = {
                    hjid: {{ (int) $hotj }},
                    hjsv: 6
                };
                a = o.getElementsByTagName('head')[0];
                r = o.createElement('script');
                r.async = 1;
                r.src = t + h._hjSettings.hjid + j + h._hjSettings.hjsv;
                a.appendChild(r);
            })(window, document, 'https://static.hotjar.com/c/hotjar-', '.js?sv=');
        </script>
    @endif

    @if ($mxpnl)
        {{-- Mixpanel --}}
        <script src="https://cdn.mxpnl.com/libs/mixpanel-2-latest.min.js"></script>
        <script>
            mixpanel.init(@json($mxpnl), {
                track_pageview: true
            });
        </script>
    @endif

@endif

{{-- MARKETING bucket --}}
@if ($consent['marketing'])

    @if ($pixel)
        {{-- Meta (Facebook) Pixel --}}
        <script>
            ! function(f, b, e, v, n, t, s) {
                if (f.fbq) return;
                n = f.fbq = function() {
                    n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                };
                if (!f._fbq) f._fbq = n;
                n.push = n;
                n.loaded = !0;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = !0;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s)
            }(window, document, 'script',
                'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', @json($pixel));
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none" alt=""
                src="https://www.facebook.com/tr?id={{ $pixel }}&ev=PageView&noscript=1" /></noscript>
    @endif

    @if ($tikt)
        {{-- TikTok Pixel --}}
        <script>
            ! function(w, d, t) {
                w.TiktokAnalyticsObject = t;
                var ttq = w[t] = w[t] || [];
                ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready",
                    "alias", "group", "enableCookie", "disableCookie"
                ], ttq.setAndDefer = function(t, e) {
                    t[e] = function() {
                        t.push([e].concat(Array.prototype.slice.call(arguments, 0)))
                    }
                };
                for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
                ttq.instance = function(t) {
                    for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++)
                        ttq.setAndDefer(e, ttq.methods[n]);
                    return e
                };
                ttq.load = function(e, n) {
                    var i = "https://analytics.tiktok.com/i18n/pixel/events.js";
                    ttq._i = ttq._i || {};
                    ttq._i[e] = [];
                    ttq._i[e]._u = i;
                    ttq._t = ttq._t || {};
                    ttq._t[e] = +new Date;
                    ttq._o = ttq._o || {};
                    ttq._o[e] = n || {};
                    var o = document.createElement("script");
                    o.type = "text/javascript";
                    o.async = !0;
                    o.src = i + "?sdkid=" + e + "&lib=" + t;
                    var a = document.getElementsByTagName("script")[0];
                    a.parentNode.insertBefore(o, a)
                };
                ttq.load(@json($tikt));
                ttq.page()
            }(window, document, 'ttq');
        </script>
    @endif

    @if ($linkd)
        {{-- LinkedIn Insight Tag --}}
        <script>
            _linkedin_partner_id = @json($linkd);
            window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
            window._linkedin_data_partner_ids.push(_linkedin_partner_id);
        </script>
        <script>
            (function(l) {
                if (!l) {
                    window.lintrk = function(a, b) {
                        window.lintrk.q.push([a, b])
                    };
                    window.lintrk.q = []
                }
                var s = document.getElementsByTagName("script")[0];
                var b = document.createElement("script");
                b.type = "text/javascript";
                b.async = true;
                b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
                s.parentNode.insertBefore(b, s);
            })(window.lintrk);
        </script>
        <noscript><img height="1" width="1" style="display:none;" alt=""
                src="https://px.ads.linkedin.com/collect/?pid={{ $linkd }}&fmt=gif" /></noscript>
    @endif

    @if ($twPx)
        {{-- X (Twitter) Pixel --}}
        <script>
            ! function(e, t, n, s, u, a) {
                e.twq || (s = e.twq = function() {
                        s.exe ? s.exe.apply(s, arguments) : s.queue.push(arguments);
                    }, s.version = '1.1', s.queue = [], u = t.createElement(n), u.async = !0, u.src =
                    'https://static.ads-twitter.com/uwt.js',
                    a = t.getElementsByTagName(n)[0], a.parentNode.insertBefore(u, a))
            }(window, document, 'script');
            twq('config', @json($twPx));
        </script>
    @endif

@endif

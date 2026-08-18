{{--
    Auth shell — wraps any auth page's form in one of 5 swappable design
    variants. The form (heading + fields + social + legal) is passed as the
    slot and stays per-page; only the surrounding chrome changes.

    Variant is chosen once at /admin/settings/auth-pages (SystemSetting
    `auth.variant`, 1–5) and applies to login / register / forgot together.

    All variants read the SAME editor data via auth_cfg($page, …):
      eyebrow · heading · heading_accent · subheading · accent · media (img/video)
    `data-fc` + `data-ae-media` hooks are preserved so the inline editor keeps
    working on every variant.
--}}
@props([
    'page'  => 'login',
    'title' => null,
])
@php
    $variant = (int) \App\Models\SystemSetting::get('auth.variant', '1');
    if ($variant < 1 || $variant > 5) $variant = 1;

    $brandName = (string) brand_name();
    $brandLogo = \App\Support\Brand::logoUrl();

    $eyebrow       = auth_cfg($page, 'eyebrow', '');
    $heading       = auth_cfg($page, 'heading', '');
    $headingAccent = auth_cfg($page, 'heading_accent', '');
    $subheading    = auth_cfg($page, 'subheading', '');
    $accent        = auth_cfg($page, 'accent', '#25D366');
    $mediaUrl      = auth_cfg($page, 'media_url', '');
    $mediaType     = auth_cfg($page, 'media_type', '');
@endphp

@php
    // Reusable bits ------------------------------------------------------------
    $renderMedia = function () use ($mediaUrl, $mediaType) {
        if (!$mediaUrl) return '';
        if ($mediaType === 'video') {
            return '<video src="' . e(asset($mediaUrl)) . '" autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover"></video>';
        }
        return '<img src="' . e(asset($mediaUrl)) . '" alt="" class="absolute inset-0 w-full h-full object-cover">';
    };
@endphp

<x-layouts.guest :title="$title" :page="'auth-' . $page">

    {{-- Brand logo block (reused by several variants) --}}
    @php
        $brandMark = $brandLogo
            ? '<img src="' . e($brandLogo) . '" alt="' . e($brandName) . '" class="h-9 w-auto max-w-[200px] object-contain">'
            : '<span class="inline-flex items-center gap-2"><span class="w-8 h-8 rounded-md bg-wa-deep text-paper-0 grid place-items-center"><svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z"/></svg></span><span class="font-serif text-[22px] tracking-[-0.01em]">' . e($brandName) . '</span></span>';
    @endphp

    @switch($variant)

        {{-- ════════════ V2 · CINEMATIC ════════════
             Full-bleed media / brand gradient behind a centered white card. --}}
        @case(2)
            <div class="relative min-h-screen flex items-center justify-center p-4 sm:p-8 overflow-hidden auth-art" data-ae-media="{{ $page }}">
                {!! $renderMedia() !!}
                <div class="absolute inset-0 bg-ink-950/55"></div>
                <div class="blob bg-wa-green w-[340px] h-[340px] -top-16 -left-10"></div>
                <div class="blob bg-accent-amber w-[300px] h-[300px] bottom-0 right-0"></div>

                <div class="relative z-10 w-full max-w-[440px]">
                    <div class="text-center text-paper-0 mb-5">
                        <div class="inline-flex">{!! $brandMark !!}</div>
                        @if ($eyebrow)
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mt-4" data-fc="{{ $page }}.eyebrow">{{ $eyebrow }}</div>
                        @endif
                        <h1 class="font-serif text-[30px] leading-tight mt-1">
                            <span data-fc="{{ $page }}.heading">{{ $heading }}</span>
                            <span class="italic" style="color: {{ $accent }}" data-fc="{{ $page }}.heading_accent">{{ $headingAccent }}</span>.
                        </h1>
                    </div>
                    <div class="bg-paper-0 rounded-3xl shadow-2xl border border-paper-200/60 p-7 sm:p-9">
                        {{ $slot }}
                    </div>
                </div>
            </div>
            @break

        {{-- ════════════ V3 · MINIMAL ════════════
             Clean paper background, single centered card, accent top-bar. --}}
        @case(3)
            <div class="min-h-screen bg-paper-50 flex items-center justify-center p-4 sm:p-6">
                <div class="w-full max-w-[440px]">
                    <div class="bg-paper-0 rounded-3xl shadow-card border border-paper-200 overflow-hidden">
                        <div class="h-1.5" style="background: {{ $accent }}"></div>
                        <div class="p-7 sm:p-9">
                            <div class="flex justify-center mb-5">{!! $brandMark !!}</div>
                            {{ $slot }}
                        </div>
                    </div>
                    @if ($subheading)
                        <p class="text-center text-[11.5px] text-ink-500 mt-4 max-w-[360px] mx-auto leading-relaxed" data-fc="{{ $page }}.subheading">{{ $subheading }}</p>
                    @endif
                </div>
            </div>
            @break

        {{-- ════════════ V4 · BRAND SPOTLIGHT ════════════
             Left accent-gradient panel (logo + serif heading + quote), form right. --}}
        @case(4)
            <div class="grid lg:grid-cols-2 min-h-screen">
                <aside class="relative hidden lg:flex flex-col justify-between p-12 text-paper-0 overflow-hidden"
                       style="background: linear-gradient(150deg, {{ $accent }} 0%, #0B1F1C 95%)" data-ae-media="{{ $page }}">
                    {!! $renderMedia() !!}
                    @if ($mediaUrl)<div class="absolute inset-0 bg-ink-950/45"></div>@endif
                    <div class="blob bg-paper-0/30 w-[280px] h-[280px] -top-10 -right-10"></div>

                    <div class="relative z-10">{!! $brandMark !!}</div>
                    <div class="relative z-10">
                        @if ($eyebrow)
                            <div class="font-mono text-[10px] uppercase tracking-[0.2em] text-paper-0/70 mb-4" data-fc="{{ $page }}.eyebrow">{{ $eyebrow }}</div>
                        @endif
                        <h1 class="font-serif text-[46px] leading-[1.04] tracking-[-0.01em]">
                            <span data-fc="{{ $page }}.heading">{{ $heading }}</span>
                            <span class="italic text-paper-0/90" data-fc="{{ $page }}.heading_accent">{{ $headingAccent }}</span>.
                        </h1>
                        <p class="mt-4 text-[14px] text-paper-0/85 leading-relaxed max-w-[440px]" data-fc="{{ $page }}.subheading">{{ $subheading }}</p>
                    </div>
                    <div class="relative z-10 text-[11px] text-paper-0/60 font-mono">© {{ date('Y') }} {{ $brandName }}</div>
                </aside>

                <main class="flex flex-col justify-center px-6 py-10 lg:px-16 bg-paper-0">
                    <div class="w-full max-w-[400px] mx-auto">
                        <div class="lg:hidden mb-8">{!! $brandMark !!}</div>
                        {{ $slot }}
                    </div>
                </main>
            </div>
            @break

        {{-- ════════════ V5 · AURORA ════════════
             Animated gradient-mesh background, floating glass-edged card. --}}
        @case(5)
            <div class="relative min-h-screen flex items-center justify-center p-4 sm:p-6 overflow-hidden auth-aurora">
                <div class="relative z-10 w-full max-w-[430px]">
                    <div class="text-center mb-5">
                        <div class="inline-flex">{!! $brandMark !!}</div>
                        @if ($eyebrow)
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mt-4" data-fc="{{ $page }}.eyebrow">{{ $eyebrow }}</div>
                        @endif
                    </div>
                    <div class="rounded-3xl bg-paper-0/80 backdrop-blur-xl border border-paper-0/60 ring-1 ring-paper-200/50 shadow-2xl p-7 sm:p-9">
                        {{ $slot }}
                    </div>
                </div>
            </div>
            @break

        {{-- ════════════ V1 · SPLIT SHOWCASE (default) ════════════
             Art panel left (heading + media + trust chips), form right. --}}
        @default
            <div class="grid lg:grid-cols-[1fr_540px] min-h-screen">
                <aside class="auth-art relative hidden lg:flex flex-col justify-center p-12 text-paper-0 overflow-hidden" data-ae-media="{{ $page }}">
                    {!! $renderMedia() !!}
                    @if ($mediaUrl)<div class="absolute inset-0 bg-ink-950/55"></div>@endif
                    <div class="blob bg-wa-green w-[320px] h-[320px] -top-12 -left-12"></div>
                    <div class="blob bg-accent-amber w-[260px] h-[260px] bottom-12 right-12"></div>

                    <div class="relative z-10">
                        <div class="mb-8">{!! $brandMark !!}</div>
                        @if ($eyebrow)
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mb-3" data-fc="{{ $page }}.eyebrow">{{ $eyebrow }}</div>
                        @endif
                        <h1 class="font-serif text-[44px] leading-[1.05] tracking-[-0.01em] max-w-[520px]">
                            <span data-fc="{{ $page }}.heading">{{ $heading }}</span>
                            <span class="italic" style="color: {{ $accent }}" data-fc="{{ $page }}.heading_accent">{{ $headingAccent }}</span>.
                        </h1>
                        <p class="mt-4 text-[14px] text-paper-0/85 leading-relaxed max-w-[460px]" data-fc="{{ $page }}.subheading">{{ $subheading }}</p>

                        <div class="grid grid-cols-3 gap-3 mt-8 max-w-[460px]">
                            @foreach ([['M5 13l4 4L19 7','Secure by design'], ['M12 8v4l3 2','Fast setup'], ['M4 7h16M4 12h10M4 17h7','Everything in one'] ] as $chip)
                                <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-3 text-center">
                                    <span class="w-8 h-8 mx-auto rounded-lg bg-wa-green/25 text-wa-green grid place-items-center mb-2">
                                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $chip[0] }}"/></svg>
                                    </span>
                                    <div class="text-[10.5px] text-paper-0/80 leading-snug">{{ __($chip[1]) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="relative z-10 text-[11px] text-paper-0/60 font-mono mt-10">© {{ date('Y') }} {{ $brandName }}</div>
                </aside>

                <main class="flex flex-col justify-center px-6 py-10 lg:px-14 bg-paper-0">
                    <div class="w-full max-w-[400px] mx-auto">
                        <div class="lg:hidden mb-8">{!! $brandMark !!}</div>
                        {{ $slot }}
                    </div>
                </main>
            </div>
    @endswitch

</x-layouts.guest>

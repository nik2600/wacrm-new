@php
    $brandName = (string) brand_name();
    $year = now()->format('Y');
    // Admin → Settings → Footer overrides (empty = sensible defaults).
    $footerTitle = (string) \App\Models\SystemSetting::get('footer_title', '');
    $footerCopyright = (string) \App\Models\SystemSetting::get('footer_copyright', '');
    $footerDescription = (string) \App\Models\SystemSetting::get('footer_description', '');
    // Footer sits on a dark panel — prefer the dark-theme logo (falls back
    // to the default logo, then to the SVG mark below).
    $brandLogo = \App\Support\Brand::logoUrl('dark');

    // Social links from admin → Settings → Site info (site_info). Only the
    // ones the admin actually filled in are rendered.
    $socials = array_filter([
        'x' => site_info('social_x'),
        'linkedin' => site_info('social_linkedin'),
        'instagram' => site_info('social_instagram'),
        'youtube' => site_info('social_youtube'),
        'facebook' => site_info('social_facebook'),
        'github' => site_info('social_github'),
    ]);
    $socialIcons = [
        'x' => '<path d="M3 3l10 10M13 3 3 13"/>',
        'linkedin' =>
            '<rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/><path d="M5 7v4M5 5v.01M8 11V8.7a1.3 1.3 0 0 1 2.6 0V11"/>',
        'instagram' =>
            '<rect x="2.5" y="2.5" width="11" height="11" rx="3"/><circle cx="8" cy="8" r="2.3"/><path d="M11.4 4.6v.01"/>',
        'youtube' => '<rect x="2" y="4" width="12" height="8" rx="2"/><path d="M7 6.4l3 1.6-3 1.6z"/>',
        'facebook' => '<path d="M10 3H8.7A2.2 2.2 0 0 0 6.5 5.2V7.5H5v2.4h1.5V14"/><path d="M6.5 8.7H10"/>',
        'github' =>
            '<path d="M6 13v-1.6c0-.5-.2-.9-.5-1.1 1.6-.2 3-.8 3-3 0-.6-.2-1.1-.6-1.5.1-.4.1-1-.1-1.4 0 0-.5-.1-1.5.6a4.6 4.6 0 0 0-2.6 0c-1-.7-1.5-.6-1.5-.6-.2.4-.2 1-.1 1.4-.4.4-.6.9-.6 1.5 0 2.2 1.4 2.8 3 3-.2.2-.4.5-.4.9"/>',
    ];
@endphp

<footer data-fc-section="footer" class="bg-ink-950 text-paper-0 relative overflow-hidden">
    <div class="absolute inset-0 dot-pattern opacity-10"></div>

    <div class="relative max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-16 grid grid-cols-1 lg:grid-cols-12 gap-8">

        {{-- Brand column --}}
        <div class="col-span-12 lg:col-span-3">
            <div class="flex items-center gap-2.5">
                @if ($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}"
                        class="h-10 w-auto max-w-[200px] object-contain">
                @else
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-wa-green text-ink-900">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor">
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z" />
                        </svg>
                    </span>
                    <span class="serif text-[28px]">{{ $footerTitle ?: $brandName }}</span>
                @endif
            </div>
            <p data-fc="footer.tagline" class="text-[13px] text-paper-0/70 mt-5 max-w-xs leading-relaxed">
                {{ fc('footer.tagline', $footerDescription ?: __('The complete WhatsApp business platform. Broadcasts, flows, inbox, AI — for teams that ship fast.')) }}
            </p>

            <div class="mt-6 hairline border-paper-0/15 rounded-xl bg-paper-0/5 p-4">
                <div data-fc="footer.status_title"
                    class="mono text-[10px] uppercase tracking-widest text-paper-0/50 mb-2">
                    {{ fc('footer.status_title', __('System status')) }}</div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-[12.5px]">
                        <span class="w-2 h-2 rounded-full bg-wa-green pulse-dot"></span>
                        <span
                            data-fc="footer.status_label">{{ fc('footer.status_label', __('All systems operational')) }}</span>
                    </div>
                    <span class="mono text-[10px] text-paper-0/50">99.98% · 90d</span>
                </div>
            </div>
        </div>

        {{-- Product --}}
        <div class="col-span-6 lg:col-span-3">
            <div data-fc="footer.col1_title" class="mono text-[10px] uppercase tracking-widest text-paper-0/50 mb-4">
                {{ fc('footer.col1_title', __('Product')) }}</div>
            <ul class="space-y-2.5 text-[13px]">
                <li><a href="{{ fc('footer.col1_link1_url', url('/')) }}" data-fc="footer.col1_link1_label"
                        data-fc-url="footer.col1_link1_url"
                        class="hover:text-wa-green">{{ fc('footer.col1_link1_label', __('Overview')) }}</a></li>
                <li><a href="{{ fc('footer.col1_link2_url', url('/features')) }}" data-fc="footer.col1_link2_label"
                        data-fc-url="footer.col1_link2_url"
                        class="hover:text-wa-green">{{ fc('footer.col1_link2_label', __('Features')) }}</a></li>
                <li><a href="{{ fc('footer.col1_link3_url', url('/pricing')) }}" data-fc="footer.col1_link3_label"
                        data-fc-url="footer.col1_link3_url"
                        class="hover:text-wa-green">{{ fc('footer.col1_link3_label', __('Pricing')) }}</a></li>
            </ul>
        </div>

        {{-- Company --}}
        <div class="col-span-6 lg:col-span-3">
            <div data-fc="footer.col4_title" class="mono text-[10px] uppercase tracking-widest text-paper-0/50 mb-4">
                {{ fc('footer.col4_title', __('Company')) }}</div>
            <ul class="space-y-2.5 text-[13px]">
                <li><a href="{{ fc('footer.col4_link1_url', url('/about')) }}" data-fc="footer.col4_link1_label"
                        data-fc-url="footer.col4_link1_url"
                        class="hover:text-wa-green">{{ fc('footer.col4_link1_label', __('About us')) }}</a></li>
                <li><a href="{{ fc('footer.col4_link2_url', url('/contact')) }}" data-fc="footer.col4_link2_label"
                        data-fc-url="footer.col4_link2_url"
                        class="hover:text-wa-green">{{ fc('footer.col4_link2_label', __('Contact us')) }}</a></li>
                <li><a href="{{ url('/login') }}" class="hover:text-wa-green">{{ __('Sign in') }}</a></li>
                <li><a href="{{ url('/register') }}" class="hover:text-wa-green">{{ __('Get started') }}</a></li>
            </ul>
        </div>

        {{-- Legal --}}
        <div class="col-span-12 lg:col-span-3">
            <div data-fc="footer.col5_title" class="mono text-[10px] uppercase tracking-widest text-paper-0/50 mb-4">
                {{ fc('footer.col5_title', __('Legal')) }}</div>
            <ul class="space-y-2.5 text-[13px]">
                <li><a href="{{ fc('footer.col5_link1_url', legal_url('terms')) }}"
                        data-fc="footer.col5_link1_label" data-fc-url="footer.col5_link1_url"
                        class="hover:text-wa-green">{{ fc('footer.col5_link1_label', __('Terms of Service')) }}</a>
                </li>
                <li><a href="{{ fc('footer.col5_link2_url', legal_url('privacy')) }}"
                        data-fc="footer.col5_link2_label" data-fc-url="footer.col5_link2_url"
                        class="hover:text-wa-green">{{ fc('footer.col5_link2_label', __('Privacy Policy')) }}</a></li>
                <li><a href="{{ fc('footer.col5_link3_url', url('/legal/refund')) }}"
                        data-fc="footer.col5_link3_label" data-fc-url="footer.col5_link3_url"
                        class="hover:text-wa-green">{{ fc('footer.col5_link3_label', __('Refund Policy')) }}</a></li>
                <li><a href="{{ fc('footer.col5_link4_url', legal_url('cookies')) }}"
                        data-fc="footer.col5_link4_label" data-fc-url="footer.col5_link4_url"
                        class="hover:text-wa-green">{{ fc('footer.col5_link4_label', __('Cookie Policy')) }}</a></li>
                <li><a href="{{ fc('footer.col5_link5_url', url('/legal/acceptable-use')) }}"
                        data-fc="footer.col5_link5_label" data-fc-url="footer.col5_link5_url"
                        class="hover:text-wa-green">{{ fc('footer.col5_link5_label', __('Acceptable Use')) }}</a></li>
            </ul>
        </div>
    </div>

    <div class="hairline-t border-paper-0/15 relative">
        <div
            class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-5 flex flex-col items-center gap-3 text-[11px] text-paper-0/55 mono">
            @if (count($socials))
                <div class="flex items-center gap-2.5">
                    @foreach ($socials as $platform => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                            title="{{ ucfirst($platform) }}" aria-label="{{ ucfirst($platform) }}"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-full border border-paper-0/15 text-paper-0/70 hover:text-wa-green hover:border-wa-green/50 transition">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.4">{!! $socialIcons[$platform] ?? '' !!}</svg>
                        </a>
                    @endforeach
                </div>
            @endif
            <span class="text-center">{{ $footerCopyright ?: ('© ' . $year . ' ' . $brandName) }}</span>
        </div>
    </div>
</footer>

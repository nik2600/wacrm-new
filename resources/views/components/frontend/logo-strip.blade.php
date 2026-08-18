{{--
 Slim logo strip with a left-hand stat label and a marquee of brand
 wordmarks. Used on the homepage and the features page right under
 the hero. Wordmarks are intentionally typographic (not images) so
 the strip stays crisp at every resolution without an asset payload.
--}}
<section class="hairline-t hairline-b bg-paper-50" data-fc-section="logo-strip">
    <div class="max-w-[1360px] mx-auto px-4 sm:px-7 py-6 flex items-center gap-5 sm:gap-10">
        <div class="mono text-[10px] uppercase tracking-[0.18em] text-ink-500 leading-tight whitespace-nowrap"
            data-fc="logo-strip.stat_label">
            {!! fc(
                'logo-strip.stat_label',
                '4,200+ ' .
                    __('teams') .
                    '<br>
             <span class="text-ink-400">240M ' .
                    __('msgs / month') .
                    '</span>',
            ) !!}
        </div>
        <div class="hairline-l h-12"></div>
        <div class="flex-1 overflow-hidden">
            <div class="marquee whitespace-nowrap text-ink-600">
                @foreach (range(0, 1) as $__loop)
                    <div class="flex items-center gap-12 shrink-0">
                        <span class="serif text-[26px]" data-fc="logo-strip.logo1_label">{!! fc('logo-strip.logo1_label', 'Bloomly') !!}</span>
                        <span class="text-[20px] font-bold tracking-tight"
                            data-fc="logo-strip.logo2_label">{!! fc('logo-strip.logo2_label', 'RIDGEWELL') !!}</span>
                        <span class="serif italic text-[24px]"
                            data-fc="logo-strip.logo3_label">{!! fc('logo-strip.logo3_label', 'maison &amp; co.') !!}</span>
                        <span class="text-[18px] mono" data-fc="logo-strip.logo4_label">{!! fc('logo-strip.logo4_label', '// kiln.studio') !!}</span>
                        <span class="text-[22px] font-bold tracking-wider"
                            data-fc="logo-strip.logo5_label">{!! fc('logo-strip.logo5_label', 'FORMAS') !!}</span>
                        <span class="serif text-[26px]" data-fc="logo-strip.logo6_label">{!! fc('logo-strip.logo6_label', 'Tokenly') !!}</span>
                        <span class="text-[20px] tracking-[0.3em]"
                            data-fc="logo-strip.logo7_label">{!! fc('logo-strip.logo7_label', 'NORTH') !!}</span>
                        <span class="text-[22px] font-semibold"
                            data-fc="logo-strip.logo8_label">{!! fc('logo-strip.logo8_label', 'Pebble.') !!}</span>
                        <span class="serif text-[24px]" data-fc="logo-strip.logo9_label">{!! fc('logo-strip.logo9_label', 'Marigold &amp; Co') !!}</span>
                        <span class="text-[20px] font-bold"
                            data-fc="logo-strip.logo10_label">{!! fc('logo-strip.logo10_label', 'CIVIC') !!}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

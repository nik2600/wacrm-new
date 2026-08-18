{{--
 Single slim activity pill — replaces the "3 stat cards" block.
 Continuous flowing green dots + 3 emoji bubbles drift left→right
 between the LIVE indicator and the 4 inline stats.
--}}
<div class="mt-20 hairline rounded-3xl lg:rounded-full bg-white relative overflow-hidden px-6 py-3.5 flex flex-wrap lg:flex-nowrap items-center justify-center lg:justify-start gap-x-5 gap-y-3 shadow-[0_18px_40px_-25px_rgba(7,94,84,0.25)]"
    data-fc-section="live-stream">

    {{-- LIVE indicator --}}
    <div class="flex items-center gap-2 shrink-0">
        <span class="relative flex w-2.5 h-2.5">
            <span class="absolute inset-0 rounded-full bg-wa-green opacity-60 spark-dot"></span>
            <span class="relative w-2.5 h-2.5 rounded-full bg-wa-green pulse-dot"></span>
        </span>
        <span class="mono text-[10px] uppercase tracking-[0.22em] text-wa-deep font-semibold"
            data-fc="live-stream.live_label">{{ fc('live-stream.live_label', __('LIVE')) }}</span>
    </div>

    <div class="w-px h-6 bg-paper-200"></div>

    {{-- flowing stream --}}
    <div class="w-full lg:w-auto lg:flex-1 relative h-7 overflow-hidden">
        <div class="absolute inset-x-0 top-1/2 h-px bg-gradient-to-r from-transparent via-wa-green/30 to-transparent">
        </div>
        <span class="stream-dot sd1"></span>
        <span class="stream-dot sd2"></span>
        <span class="stream-dot sd3"></span>
        <span class="stream-dot sd4"></span>
        <span class="stream-dot sd5"></span>
        <span class="stream-dot sd6"></span>
        <span class="stream-bub sb1">🌷</span>
        <span class="stream-bub sb2">✓✓</span>
        <span class="stream-bub sb3">📦</span>
    </div>

    <div class="w-px h-6 bg-paper-200"></div>

    {{-- inline stats --}}
    <div class="flex flex-wrap items-center justify-center gap-4 shrink-0">
        <div class="flex items-baseline gap-1.5">
            <span class="serif text-[22px] leading-none tabular text-ink-900">240<span
                    class="text-wa-deep">M</span></span>
            <span class="mono text-[9px] uppercase tracking-widest text-ink-500"
                data-fc="live-stream.stat1_label">{{ fc('live-stream.stat1_label', __('msgs · 30d')) }}</span>
        </div>
        <span class="text-ink-400">·</span>
        <div class="flex items-baseline gap-1.5">
            <span class="serif text-[22px] leading-none tabular text-ink-900">4,218</span>
            <span class="mono text-[9px] uppercase tracking-widest text-ink-500"
                data-fc="live-stream.stat2_label">{{ fc('live-stream.stat2_label', __('teams')) }}</span>
        </div>
        <span class="text-ink-400">·</span>
        <div class="flex items-baseline gap-1.5">
            <span class="serif text-[22px] leading-none tabular text-ink-900">38</span>
            <span class="mono text-[9px] uppercase tracking-widest text-ink-500"
                data-fc="live-stream.stat3_label">{{ fc('live-stream.stat3_label', __('markets')) }}</span>
        </div>
        <span class="text-ink-400">·</span>
        <div class="flex items-baseline gap-1.5">
            <span class="serif text-[22px] leading-none tabular text-wa-deep">98.4<span
                    class="text-[15px]">%</span></span>
            <span class="mono text-[9px] uppercase tracking-widest text-ink-500"
                data-fc="live-stream.stat4_label">{{ fc('live-stream.stat4_label', __('deliv')) }}</span>
        </div>
    </div>
</div>

{{--
 Top dark marquee — runs above the nav on every public page.

 Admin-driven: it pulls the SAME active announcements the user shell's
 announcement-bar uses (managed at /admin/announcements, cached 60s). Whatever
 the operator publishes there drives this public ticker too. When nothing is
 published we fall back to the live-editable editorial copy (fc()) so the
 marketing strip is never empty.
--}}
@php
    $annRows = \Illuminate\Support\Facades\Cache::remember(
        'announcements.active.v1',
        60,
        fn () => \App\Models\Announcement::active()->get(),
    );
@endphp
<div data-fc-section="ticker" class="bg-ink-950 text-paper-0 text-[11px] mono overflow-hidden">
    <div class="marquee whitespace-nowrap py-2.5">
        @foreach (range(0, 1) as $__loop)
            <div class="flex items-center gap-8 shrink-0 px-4">
                @if ($annRows->isNotEmpty())
                    {{-- Admin announcements drive the ticker. --}}
                    @foreach ($annRows as $__i => $a)
                        @if ($__i > 0)
                            <span class="text-paper-0/40">·</span>
                        @endif
                        <span class="flex items-center gap-2 {{ $a->tone === 'success' ? 'text-wa-green' : '' }}">
                            @if ($__i === 0)
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>
                            @endif
                            @if ($a->link_url)
                                <a href="{{ $a->link_url }}" class="hover:underline">{{ $a->text }}@if ($a->link_label)<span class="opacity-70"> · {{ $a->link_label }} →</span>@endif</a>
                            @else
                                <span>{{ $a->text }}</span>
                            @endif
                        </span>
                    @endforeach
                @else
                    {{-- No published announcements → editorial default (live-editable). --}}
                    <span class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>
                        <span
                            data-fc="ticker.message1">{{ fc('ticker.message1', '240,184,209 messages delivered this month') }}</span>
                    </span>
                    <span class="text-paper-0/40">·</span>
                    <span
                        data-fc="ticker.message2">{{ fc('ticker.message2', '4,218 active workspaces across 38 markets') }}</span>
                    <span class="text-paper-0/40">·</span>
                    <span data-fc="ticker.message3">{{ fc('ticker.message3', 'Median template approval · 18m') }}</span>
                    <span class="text-paper-0/40">·</span>
                    <span class="text-wa-green"
                        data-fc="ticker.message4">{{ fc('ticker.message4', '▲ +34% revenue lift · 90 days post-onboarding') }}</span>
                    <span class="text-paper-0/40">·</span>
                    <span data-fc="ticker.message5">{{ fc('ticker.message5', 'Now live: AI Flow Generation') }}</span>
                @endif
            </div>
        @endforeach
    </div>
</div>

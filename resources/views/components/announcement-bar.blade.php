@php
    // Pull active announcements once per request — cached for 60s so the
    // user dashboard hot-path isn't hammering the DB on every page hit.
$rows = \Illuminate\Support\Facades\Cache::remember(
    'announcements.active.v1',
        60,
        fn() => \App\Models\Announcement::active()->get(),
    );
    if ($rows->isEmpty()) {
        return;
    }
@endphp
{{-- One bar per tone: same-tone announcements chain together with separators
 so the colour scheme stays consistent across the strip. --}}
@foreach ($rows->groupBy('tone') as $tone => $group)
    @php
        $palette = $group->first()->toneClasses();
        // Stable token so localStorage dismissal can persist across reloads.
        $token = 'ann-' . $group->pluck('id')->join('-');
    @endphp
    <div data-announcement-bar data-token="{{ $token }}" class="w-full overflow-hidden border-b"
        style="background: {{ $palette['bg'] }}; color: {{ $palette['text'] }}; border-bottom-color: rgba(255,255,255,0.08);">
        <div class="relative h-9 flex items-center">
            {{-- Two copies of the content side-by-side so the CSS-animated
 marquee loops seamlessly when the keyframes hit -50%. --}}
            <div class="flex animate-marquee whitespace-nowrap will-change-transform"
                style="animation-duration: {{ max(20, $group->sum(fn($a) => mb_strlen($a->text)) / 5) }}s;">
                @for ($pass = 0; $pass < 2; $pass++)
                    @foreach ($group as $a)
                        <span
                            class="inline-flex items-center gap-2 px-6 text-[11.5px] font-mono uppercase tracking-[0.16em]">
                            @if ($a->link_url)
                                <a href="{{ $a->link_url }}" class="hover:underline"
                                    style="color: {{ $palette['text'] }};">{{ $a->text }}@if ($a->link_label)
                                        <span class="ml-2 opacity-80">{{ $a->link_label }} →</span>
                                    @endif
                                </a>
                            @else
                                {{ $a->text }}
                            @endif
                            <span class="opacity-30">·</span>
                        </span>
                    @endforeach
                @endfor
            </div>

            @if ($group->contains(fn($a) => $a->dismissible))
                <button type="button" data-announcement-dismiss
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full grid place-items-center hover:bg-white/10"
                    style="color: {{ $palette['text'] }};" title="{{ __('Dismiss') }}">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 3l10 10M13 3 3 13" />
                    </svg>
                </button>
            @endif
        </div>
    </div>
@endforeach

@once
    <style>
        @keyframes wadesk-marquee {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        .animate-marquee {
            animation: wadesk-marquee linear infinite;
        }

        [data-announcement-bar]:hover .animate-marquee {
            animation-play-state: paused;
        }
    </style>
    <script>
        (() => {
            document.querySelectorAll('[data-announcement-bar]').forEach((bar) => {
                const token = bar.dataset.token;
                if (token && localStorage.getItem('dismissed:' + token) === '1') bar.remove();
            });
            document.querySelectorAll('[data-announcement-dismiss]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const bar = btn.closest('[data-announcement-bar]');
                    if (bar?.dataset.token) localStorage.setItem('dismissed:' + bar.dataset.token, '1');
                    bar?.remove();
                });
            });
        })
        ();
    </script>
@endonce

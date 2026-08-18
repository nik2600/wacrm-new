@props([
    /** Page title (also used in <title> tag). */
    'title' => 'Legal',
    /** Subtitle / one-liner under the headline. */
    'subtitle' => null,
    /** Last-updated date (e.g. "March 14, 2026"). */
    'updatedAt' => null,
    /** Effective-date string (often the same as updatedAt). */
    'effective' => null,
    /**
     * Sections array — each item: ['n' => '01', 'title' => '...', 'body' => '<p>html…</p>'].
     * The component auto-generates anchors (sec-NN) and a TOC.
     */
    'sections' => [],
])

<x-layouts.frontend :title="$title" nav-key="" :page="'frontend-legal-' . \Illuminate\Support\Str::slug($title)">

    {{-- ============== HERO ============== --}}
    <section class="relative overflow-hidden bg-paper-0">
        <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-32 -right-32 w-[420px] h-[420px] rounded-full bg-wa-mint/40 blur-bub"></div>

        <div class="relative max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-7 py-24">
            <span class="badge-num mb-6 inline-block">— {{ __('Legal') }}</span>
            <h1 class="serif text-[40px] sm:text-[56px] lg:text-[88px] leading-[0.92] tracking-[-0.025em]">
                {{ $title }}
            </h1>
            @if ($subtitle)
                <p class="text-[16px] text-ink-700 mt-6 max-w-2xl leading-relaxed">{{ $subtitle }}</p>
            @endif
            <div class="mt-8 flex flex-wrap items-center gap-3 text-[11px] mono uppercase tracking-widest text-ink-500">
                @if ($updatedAt)
                    <span class="inline-flex items-center gap-2 hairline rounded-full bg-white px-3 py-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                        {{ __('Updated') }} · {{ $updatedAt }}
                    </span>
                @endif
                @if ($effective)
                    <span
                        class="inline-flex items-center gap-2 hairline rounded-full bg-white px-3 py-1.5 text-wa-deep">
                        {{ __('Effective') }} · {{ $effective }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-2 hairline rounded-full bg-white px-3 py-1.5">
                    {{ __('Read time') }} · {{ max(1, intval(count($sections) * 1.5)) }} {{ __('min') }}
                </span>
            </div>
        </div>
    </section>

    {{-- ============== BODY ============== --}}
    <section class="bg-white">
        <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-16 grid grid-cols-1 lg:grid-cols-12 gap-10">

            {{-- TOC sidebar --}}
            <aside class="col-span-12 lg:col-span-3">
                <div class="lg:sticky lg:top-24">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-4">{{ __('Contents') }}
                    </div>
                    <ol class="space-y-2.5">
                        @foreach ($sections as $s)
                            <li>
                                <a href="#sec-{{ $s['n'] ?? $loop->iteration }}"
                                    class="flex gap-3 text-[12.5px] text-ink-700 hover:text-wa-deep">
                                    <span
                                        class="serif text-wa-deep w-6 shrink-0">{{ $s['n'] ?? sprintf('%02d', $loop->iteration) }}</span>
                                    <span>{{ $s['title'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>

                    <div class="hairline rounded-2xl bg-paper-50 p-4 mt-8">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2">
                            {{ __('Questions?') }}</div>
                        <p class="text-[12px] text-ink-700 leading-relaxed">
                            @php($legalEmail = brand_email('legal'))
                            {{ __('Email') }} <a href="mailto:{{ $legalEmail }}"
                                class="text-wa-deep font-semibold">{{ $legalEmail }}</a>
                            — {{ __('our team replies inside 4 hours.') }}
                        </p>
                    </div>
                </div>
            </aside>

            {{-- Article body --}}
            <article class="col-span-12 lg:col-span-9 legal-prose">
                {{ $slot ?? '' }}

                @foreach ($sections as $s)
                    <section id="sec-{{ $s['n'] ?? $loop->iteration }}" class="mb-16 reveal">
                        <div class="flex items-baseline gap-5 mb-5 hairline-b pb-4">
                            <span
                                class="serif text-[40px] leading-none text-wa-deep">{{ $s['n'] ?? sprintf('%02d', $loop->iteration) }}</span>
                            <h2 class="serif text-[36px] leading-none">{{ $s['title'] }}</h2>
                        </div>
                        <div class="prose-body text-[14.5px] leading-[1.7] text-ink-800">
                            {!! $s['body'] !!}
                        </div>
                    </section>
                @endforeach

                {{-- Last block --}}
                <div class="hairline-t pt-8 mt-12 text-[12px] mono text-ink-500">
                    {{ __('Document version') }} · {{ $updatedAt ?? __('current') }} ·
                    <a href="mailto:{{ brand_email('legal') }}" class="text-wa-deep">{{ brand_email('legal') }}</a>
                </div>
            </article>
        </div>
    </section>

    <style>
        .legal-prose .prose-body p {
            margin: 0 0 1em;
        }

        .legal-prose .prose-body ul,
        .legal-prose .prose-body ol {
            margin: 0 0 1em 1.5em;
        }

        .legal-prose .prose-body li {
            margin-bottom: 0.4em;
        }

        .legal-prose .prose-body strong {
            color: #0B1F1C;
            font-weight: 600;
        }

        .legal-prose .prose-body a {
            color: #075E54;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .legal-prose .prose-body h3 {
            font-family: 'Instrument Serif', serif;
            font-size: 22px;
            margin: 1.5em 0 .6em;
        }

        .legal-prose .prose-body code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            background: #F5F3EC;
            padding: 1px 6px;
            border-radius: 4px;
        }
    </style>

</x-layouts.frontend>

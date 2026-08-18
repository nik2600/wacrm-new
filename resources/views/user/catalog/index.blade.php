<x-layouts.user :title="__('WhatsApp Catalog')" nav-key="more" page="user-catalog-index">
    @php
        $tab = $tab ?? 'setup';
    @endphp

    {{-- ───── Sub-header / breadcrumb strip ───── --}}
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/integrations') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to integrations') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Integrations / WhatsApp Catalog') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Push products into') }} <span
                            class="italic text-wa-deep">{{ __('WhatsApp chats') }}</span></div>
                </div>
            </div>
            @if ($catalog ?? null)
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ strtoupper(str_replace('_', ' ', $catalog->provider)) }}
                    · connected</span>
            @else
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-mono bg-paper-50 text-ink-500 border border-paper-200"><span
                        class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>Not connected</span>
            @endif
        </div>
    </div>

    {{-- ───── Tab strip ───── --}}
    <div class="border-b border-paper-200 bg-paper-0/60">
        <nav class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 flex items-center gap-1 overflow-x-auto">
            @php
                $tabs = [
                    'setup' => ['Setup', route('user.catalog.index')],
                    'send' => ['Send', route('user.catalog.send')],
                    'collections' => ['Collections', route('user.catalog.collections')],
                    'activity' => ['Activity', route('user.catalog.activity')],
                ];
            @endphp
            @foreach ($tabs as $key => [$label, $href])
                <a href="{{ $href }}"
                    class="relative shrink-0 whitespace-nowrap px-4 py-3 text-[12.5px] font-medium {{ $tab === $key ? 'text-wa-deep' : 'text-ink-700 hover:text-wa-deep' }}">
                    {{ $label }}
                    @if ($tab === $key)
                        <span class="absolute left-3 right-3 -bottom-px h-0.5 bg-wa-deep rounded-full"></span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7 space-y-5">
        @if (session('status'))
            <div class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div
                class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F]">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- ════════════════ SETUP TAB ═════════════════════════════ --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        @if ($tab === 'setup')
            @include('user.catalog._setup', [
                'catalog' => $catalog ?? null,
                'shops' => $shops ?? collect(),
                'products' => $products ?? collect(),
                'statusBuckets' => $statusBuckets ?? [],
                'totalProducts' => $totalProducts ?? 0,
            ])

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- ════════════════ SEND TAB ══════════════════════════════ --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
        @elseif ($tab === 'send')
            @include('user.catalog._send', [
                'catalog' => $catalog ?? null,
                'devices' => $devices ?? collect(),
                'senders' => $senders ?? collect(),
                'totalProducts' => $totalProducts ?? 0,
                'recentSends' => $recentSends ?? collect(),
            ])

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- ════════════════ COLLECTIONS TAB ═══════════════════════ --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
        @elseif ($tab === 'collections')
            @include('user.catalog._collections', [
                'catalog' => $catalog ?? null,
                'sets' => $sets ?? collect(),
                'pickProducts' => $pickProducts ?? collect(),
                'devices' => $devices ?? collect(),
                'senders' => $senders ?? collect(),
                'totalProducts' => $totalProducts ?? 0,
            ])

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- ════════════════ ACTIVITY TAB ══════════════════════════ --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
        @elseif ($tab === 'activity')
            @include('user.catalog._activity', [
                'recentSends' => $recentSends ?? collect(),
            ])
        @endif
    </main>

</x-layouts.user>

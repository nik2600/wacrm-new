@php
    $rows = $rows ?? collect();
    $typePill =
        $typePill ??
        function ($r) {
            if ($r->reply_type === 'flow') {
                $flowName = trim((string) optional($r->flow)->flow_name);
                return [
                    'cls' => 'bg-[#D9E5F2] text-[#13478A]',
                    'label' => $flowName !== '' ? 'Flow / ' . $flowName : 'Flow',
                ];
            }

            return match ($r->message_type ?: 'text') {
                'image' => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'label' => 'Custom / Image'],
                'video' => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'label' => 'Custom / Video'],
                'document' => ['cls' => 'bg-accent-amber/20 text-[#7B5A14]', 'label' => 'Custom / Document'],
                'template' => ['cls' => 'bg-[#F3E9FF] text-[#5B3D8A]', 'label' => 'Custom / Template'],
                default => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'label' => 'Custom / Text'],
            };
        };
    $tilePalette = $tilePalette ?? [
        ['cls' => 'bg-wa-mint text-wa-deep'],
        ['cls' => 'bg-[#D9E5F2] text-[#13478A]'],
        ['cls' => 'bg-accent-amber/20 text-[#7B5A14]'],
        ['cls' => 'bg-[#F3E9FF] text-[#5B3D8A]'],
        ['cls' => 'bg-accent-coral/15 text-[#A1431F]'],
        ['cls' => 'bg-[#E8F5E9] text-wa-deep'],
    ];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-3">
    @forelse ($rows as $i => $row)
        @php
            $tile = $tilePalette[$i % count($tilePalette)];
            $kw = $row->keyword ?: '-';
            $tag = mb_strtoupper(mb_substr($kw, 0, 2));
            $tp = $typePill($row);
            $matchSub = match ($row->matching_method) {
                'fuzzy' => 'fuzzy / ' . ($row->fuzzy_similarity ?: 80) . '%',
                'contains' => 'contains',
                default => 'exact',
            };
            $deviceName = trim((string) optional($row->device)->device_name);
            $devicePhone = trim((string) optional($row->device)->phone_number);
            // WABA / Twilio rules pin a wa_provider_configs row (not a devices
            // row) → relationship is null. Fall back so the number shows here too.
            if ($devicePhone === '' && $deviceName === '' && $row->device_id) {
                $cfg = \App\Models\WaProviderConfig::find($row->device_id);
                if ($cfg) {
                    $devicePhone = trim((string) $cfg->phone_number);
                    $deviceName  = trim((string) ($cfg->display_label ?: strtoupper((string) $cfg->provider)));
                }
            }
            // Instagram rules pin an Instaflow account id (not a devices row) →
            // resolve the linked IG account so the card shows the handle, not blank.
            if ($devicePhone === '' && $deviceName === '' && strtolower((string) ($row->provider ?? '')) === 'instagram' && $row->device_id) {
                $ig = \App\Models\WorkspaceIgAccount::where('instaflow_account_id', $row->device_id)->first();
                $igHandle = $ig ? trim((string) ($ig->username ?? '')) : '';
                $igName   = $ig ? trim((string) ($ig->name ?? '')) : '';
                $devicePhone = $igHandle !== '' ? '@' . ltrim($igHandle, '@') : ($igName !== '' ? $igName : ('IG account #' . $row->device_id));
                $deviceName  = ($igName !== '' && $igName !== $igHandle) ? $igName : 'Instagram';
            }
            $content = trim((string) optional($row->selectedContents->first())->content);
        @endphp
        <article
            class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card hover:border-wa-deep/40 transition"
            data-row-id="{{ $row->id }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span
                        class="w-10 h-10 rounded-xl {{ $tile['cls'] }} grid place-items-center text-[11px] font-mono shrink-0">{{ $tag }}</span>
                    <div class="min-w-0">
                        <h3 class="font-serif text-[22px] leading-tight truncate">{{ $kw }}</h3>
                        <div class="text-[10.5px] text-ink-500 font-mono mt-1">{{ $matchSub }}</div>
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer shrink-0">
                    <span class="relative inline-flex items-center">
                        <input type="checkbox" class="sr-only peer" data-toggle-row="{{ $row->id }}"
                            data-toggle-label {{ $row->status ? 'checked' : '' }}>
                        <span
                            class="w-9 h-5 rounded-full bg-paper-200 peer-checked:bg-wa-deep transition-colors"></span>
                        <span
                            class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-paper-0 shadow transition-transform peer-checked:translate-x-[16px]"></span>
                    </span>
                    <span class="text-[11px] font-mono {{ $row->status ? 'text-wa-deep' : 'text-ink-500' }}"
                        data-toggle-text data-on="Active" data-off="Paused" data-on-class="text-wa-deep"
                        data-off-class="text-ink-500">{{ $row->status ? 'Active' : 'Paused' }}</span>
                </label>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $tp['cls'] }} text-[10.5px] font-mono">{{ $tp['label'] }}</span>
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-[10.5px] font-mono text-ink-600">{{ number_format((int) $row->trigger_count) }}
                    {{ __('triggers') }}</span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-[12px]">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Device') }}
                    </div>
                    <div class="font-mono text-[11.5px] text-ink-800 mt-1">
                        {{ $devicePhone !== '' ? $devicePhone : '-' }}</div>
                    @if ($deviceName !== '')
                        <div class="text-[10.5px] text-ink-500 mt-0.5">{{ $deviceName }}</div>
                    @endif
                </div>
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Updated') }}
                    </div>
                    <div class="font-mono text-[11.5px] text-ink-800 mt-1">
                        {{ optional($row->updated_at)->diffForHumans() ?: '-' }}</div>
                </div>
            </div>

            @if ($content !== '')
                <p class="mt-4 text-[12.5px] text-ink-600 leading-relaxed line-clamp-2">{{ $content }}</p>
            @endif

            <div class="mt-4 pt-3 border-t border-paper-200 flex items-center justify-between">
                <a href="{{ url('/auto-reply/keyword') }}?k={{ urlencode($kw) }}"
                    class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Open analytics') }}</a>
                <div class="flex items-center gap-1">
                    <a href="{{ url('/auto-reply/create') }}?id={{ $row->id }}"
                        class="w-8 h-8 rounded-full border border-paper-200 hover:bg-paper-50 inline-flex items-center justify-center"
                        title="{{ __('Edit') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                        </svg>
                    </a>
                    <button type="button" data-row-action="delete" data-row-id="{{ $row->id }}"
                        data-row-name="{{ $kw }}"
                        class="w-8 h-8 rounded-full border border-paper-200 hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                        title="{{ __('Delete') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                        </svg>
                    </button>
                </div>
            </div>
        </article>
    @empty
        @include('user.partials.empty-state', [
            'class' => 'lg:col-span-2 2xl:col-span-3',
            'message' =>
                'No auto-reply rules match the current filters. Try clearing filters or create a new auto reply.',
            'resetButtonAttrs' => 'data-ar-reset',
            'actionHref' => url('/auto-reply/create'),
            'actionLabel' => 'Create auto reply',
        ])
    @endforelse
</div>

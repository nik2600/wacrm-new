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
        // WABA / Twilio rules pin a wa_provider_configs row (not a `devices`
        // row), so the device relationship is null and the cell rendered blank.
        // Fall back to the matching config so the number shows in the list the
        // same way it shows in the edit form. Paginated to 12 rows → trivial.
        if ($devicePhone === '' && $deviceName === '' && $row->device_id) {
            $cfg = \App\Models\WaProviderConfig::find($row->device_id);
            if ($cfg) {
                $devicePhone = trim((string) $cfg->phone_number);
                $deviceName  = trim((string) ($cfg->display_label ?: strtoupper((string) $cfg->provider)));
            }
        }
        // Provider badge — same palette as /broadcasts + /devices + /team-inbox
        // so a multi-engine workspace can recognise at a glance which engine
        // each rule belongs to.
        $rowProv = strtolower((string) ($row->provider ?? ''));
        // Instagram rules pin an Instaflow account id (not a `devices` row), so the
        // device relationship + the WABA fallback above both miss and the sender
        // cell rendered blank. Resolve the linked IG account so it shows the handle
        // (@username · Instagram) instead of "-", matching the WhatsApp/WABA rows.
        if ($devicePhone === '' && $deviceName === '' && $rowProv === 'instagram' && $row->device_id) {
            $ig = \App\Models\WorkspaceIgAccount::where('instaflow_account_id', $row->device_id)->first();
            $igHandle = $ig ? trim((string) ($ig->username ?? '')) : '';
            $igName   = $ig ? trim((string) ($ig->name ?? '')) : '';
            $devicePhone = $igHandle !== '' ? '@' . ltrim($igHandle, '@') : ($igName !== '' ? $igName : ('IG account #' . $row->device_id));
            $deviceName  = ($igName !== '' && $igName !== $igHandle) ? $igName : 'Instagram';
        }
        $rowProvBadge = match ($rowProv) {
            'baileys' => ['Unofficial API', 'bg-ink-900/5 text-ink-700 border border-ink-200'],
            'waba' => ['WABA', 'bg-wa-mint/40 text-wa-deep border border-wa-deep/20'],
            'twilio' => ['Twilio', 'bg-[#F22F46]/10 text-[#A12534] border border-[#F22F46]/25'],
            'instagram' => ['Instagram', 'bg-[#E4405F]/10 text-[#C13584] border border-[#E4405F]/25'],
            default => null,
        };
        $lastFired = $row->last_triggered_at?->diffForHumans(null, true);
    @endphp
    <tr class="hover:bg-paper-50/60 transition-colors" data-row-id="{{ $row->id }}">
        <td class="px-4 py-3">
            <input type="checkbox" data-bulk-row="{{ $row->id }}"
                class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep" />
        </td>
        <td class="px-2 py-3">
            <div class="flex items-center gap-2.5">
                <span
                    class="w-8 h-8 rounded-lg {{ $tile['cls'] }} grid place-items-center text-[10px] font-mono shrink-0">{{ $tag }}</span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="font-semibold text-ink-900 truncate">{{ $kw }}</div>
                        @if ($rowProvBadge)
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded-full {{ $rowProvBadge[1] }} text-[9.5px] font-mono font-semibold uppercase tracking-wider shrink-0">{{ $rowProvBadge[0] }}</span>
                        @endif
                    </div>
                    <div class="text-[10.5px] text-ink-500 font-mono">{{ $matchSub }}</div>
                </div>
            </div>
        </td>
        <td class="px-2 py-3">
            <div class="font-mono text-[11.5px] text-ink-700">{{ $devicePhone !== '' ? $devicePhone : '-' }}</div>
            @if ($deviceName !== '')
                <div class="text-[10.5px] text-ink-500">{{ $deviceName }}</div>
            @endif
        </td>
        <td class="px-2 py-3">
            <span
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $tp['cls'] }} text-[10.5px] font-mono">{{ $tp['label'] }}</span>
        </td>
        <td class="px-2 py-3">
            <div class="flex items-center gap-2">
                <span
                    class="font-semibold text-ink-900 tabular-nums">{{ number_format((int) $row->trigger_count) }}</span>
                <span class="text-[10px] text-ink-500 font-mono">{{ __('all-time') }}</span>
            </div>
            @if ($lastFired)
                <div class="text-[10px] text-ink-500 mt-0.5">{{ __('last') }} {{ $lastFired }}
                    {{ __('ago') }}</div>
            @endif
        </td>
        <td class="px-2 py-3">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <span class="relative inline-flex items-center">
                    <input type="checkbox" class="sr-only peer" data-toggle-row="{{ $row->id }}" data-toggle-label
                        {{ $row->status ? 'checked' : '' }}>
                    <span class="w-9 h-5 rounded-full bg-paper-200 peer-checked:bg-wa-deep transition-colors"></span>
                    <span
                        class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-paper-0 shadow transition-transform peer-checked:translate-x-[16px]"></span>
                </span>
                <span class="text-[11px] font-mono {{ $row->status ? 'text-wa-deep' : 'text-ink-500' }}"
                    data-toggle-text data-on="Active" data-off="Paused" data-on-class="text-wa-deep"
                    data-off-class="text-ink-500">{{ $row->status ? 'Active' : 'Paused' }}</span>
            </label>
        </td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
            <a href="{{ url('/auto-reply/keyword') }}?k={{ urlencode($kw) }}"
                class="w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep inline-flex items-center justify-center"
                title="{{ __('View analytics') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                    <circle cx="8" cy="8" r="2" />
                </svg>
            </a>
            <a href="{{ url('/auto-reply/create') }}?id={{ $row->id }}"
                class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                title="{{ __('Edit') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                </svg>
            </a>
            <button type="button" data-row-action="delete" data-row-id="{{ $row->id }}"
                data-row-name="{{ $kw }}"
                class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                title="{{ __('Delete') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                </svg>
            </button>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="p-4">
            @include('user.partials.empty-state', [
                'message' =>
                    'No auto-reply rules match the current filters. Try clearing filters or create a new auto reply.',
                'resetButtonAttrs' => 'data-ar-reset',
                'actionHref' => url('/auto-reply/create'),
                'actionLabel' => 'Create auto reply',
            ])
        </td>
    </tr>
@endforelse

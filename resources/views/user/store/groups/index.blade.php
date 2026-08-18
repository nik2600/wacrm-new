<x-layouts.user :title="__('WhatsApp Groups')" nav-key="connect" page="user-store-groups-index">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $sf = $u?->current_workspace_id
            ? \App\Models\WaStorefront::where('workspace_id', $u->current_workspace_id)->first()
            : null;
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'groups', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 min-w-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">{{ __('Store / Groups') }}</div>
                    <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">{{ __('WhatsApp Groups') }}</h1>
                    <p class="text-[13px] text-ink-600 mt-1">
                        {{ __('Groups your connected number belongs to. When an order is confirmed, :brand posts it into the customer\'s group and @mentions them. Add your number to a group on WhatsApp — it appears here automatically.', ['brand' => brand_name()]) }}</p>
                </div>

                @if (session('status'))
                    <div class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">{{ session('status') }}</div>
                @endif
                @error('group_code')
                    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-2 text-[12.5px] text-red-700 font-mono">{{ $message }}</div>
                @enderror

                {{-- KPIs --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Groups synced') }}</div>
                        <div class="font-serif text-[28px] leading-tight mt-1">{{ number_format($total) }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('With ordering code') }}</div>
                        <div class="font-serif text-[28px] leading-tight mt-1">{{ number_format($coded) }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Bot number') }}</div>
                        <div class="font-mono text-[15px] leading-tight mt-2 text-ink-800">{{ $bot ? '+' . $bot : __('— connect a device') }}</div>
                    </div>
                </div>

                <form method="GET" class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center gap-2 flex-wrap">
                        <div class="relative flex-1 min-w-[260px] max-w-[420px]">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6">
                                <circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" />
                            </svg>
                            <input name="q" type="search" value="{{ $q }}" placeholder="{{ __('Search group name...') }}"
                                class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </div>
                        <button type="submit" class="px-3.5 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold">{{ __('Search') }}</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead>
                                <tr class="text-left text-ink-500 font-mono text-[10.5px] uppercase tracking-[0.12em] border-b border-paper-200">
                                    <th class="px-4 py-2.5 font-medium">{{ __('Group') }}</th>
                                    <th class="px-4 py-2.5 font-medium">{{ __('Members') }}</th>
                                    <th class="px-4 py-2.5 font-medium">{{ __('Bot device') }}</th>
                                    <th class="px-4 py-2.5 font-medium">{{ __('Synced') }}</th>
                                    <th class="px-4 py-2.5 font-medium">{{ __('Ordering code') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-100">
                                @forelse ($groups as $g)
                                    <tr class="hover:bg-paper-50/60 align-top">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-ink-900">{{ $g->subject ?: __('(unnamed group)') }}</div>
                                            <div class="font-mono text-[10px] text-ink-400 mt-0.5 truncate max-w-[220px]">{{ $g->group_jid }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-ink-700">{{ number_format($g->size) }}</td>
                                        <td class="px-4 py-3 font-mono text-[11px] text-ink-600">{{ $g->device_phone ? '+' . $g->device_phone : '—' }}</td>
                                        <td class="px-4 py-3 text-ink-600">{{ $g->synced_at ? $g->synced_at->diffForHumans() : '—' }}</td>
                                        <td class="px-4 py-3">
                                            <form method="POST" action="{{ route('user.store.groups.code', $g->id) }}" class="flex items-center gap-1.5">
                                                @csrf @method('PUT')
                                                <input name="group_code" value="{{ $g->group_code }}" placeholder="{{ __('e.g. ACME') }}" maxlength="48"
                                                    class="w-28 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-white font-mono text-[11.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-paper-100 hover:bg-paper-200 text-ink-700 text-[11px] font-semibold">{{ __('Save') }}</button>
                                            </form>
                                            @if ($bot && $g->group_code)
                                                @php $link = 'https://wa.me/' . $bot . '?text=' . rawurlencode('ORDER G:' . $g->group_code); @endphp
                                                <button type="button"
                                                    onclick="navigator.clipboard.writeText('{{ $link }}'); this.textContent='{{ __('Copied!') }}'; setTimeout(()=>this.textContent='{{ __('Copy ordering link') }}', 1500)"
                                                    class="mt-1.5 inline-flex items-center gap-1 text-[11px] text-wa-deep font-semibold hover:underline">
                                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 5V3.5A1.5 1.5 0 0 1 6.5 2h6A1.5 1.5 0 0 1 14 3.5v6A1.5 1.5 0 0 1 12.5 11H11" /><rect x="2" y="5" width="9" height="9" rx="1.5" /></svg>
                                                    {{ __('Copy ordering link') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-12 text-center">
                                            <div class="text-ink-700 font-semibold">{{ __('No groups synced yet') }}</div>
                                            <p class="text-[12.5px] text-ink-500 mt-1 max-w-md mx-auto">
                                                {{ __('Add your connected WhatsApp number to a group, then send any message in that group. :brand mirrors the group here within a minute (Unofficial API only).', ['brand' => brand_name()]) }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                @if ($groups->hasPages())
                    <div>{{ $groups->links() }}</div>
                @endif

                <div class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1">{{ __('How ordering codes work') }}</div>
                    {{ __('Give a group a short code (e.g. ACME), then share its ordering link. When a customer taps it and confirms an order, :brand posts the order straight into that exact group and @mentions them — even if they belong to several groups.', ['brand' => brand_name()]) }}
                </div>
            </section>
        </div>
    </main>
</x-layouts.user>

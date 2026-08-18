<x-layouts.admin :title="__('Admin · Add-ons')" admin-key="addons" page="addons-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Add-ons') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Add-ons') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Add') }}
                    <span class="italic text-wa-deep">{{ __('-ons') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('À-la-carte feature packs a customer buys ON TOP of their plan — e.g. "Campaigns add-on" or "+1 WhatsApp number". Each add-on grants the feature toggles / limits you set on it, merged onto the customer\'s plan. They appear on the customer\'s Account page once they\'re on an active plan.') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.addons.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    {{ __('New add-on') }}
                </a>
            </div>
        </div>

        <x-admin.flash />

        @php $catalog = \App\Models\Package::featureCatalog(); @endphp

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[12.5px]">
                    <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                        <tr>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">{{ __('Name') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">{{ __('Price') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">{{ __('Period') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">{{ __('Grants') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">{{ __('Status') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[210px]">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-100">
                        @forelse ($addons as $a)
                            @php
                                $grants = [];
                                foreach ($catalog['capabilities'] as $k => $l) { if ((bool) ($a->{$k} ?? false)) $grants[] = $l; }
                                foreach ($catalog['limits'] as $k => $l) { if (($a->{$k} ?? null) !== null && (int) $a->{$k} !== 0) $grants[] = '+' . (int) $a->{$k} . ' ' . $l; }
                            @endphp
                            <tr class="hover:bg-paper-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-ink-900">{{ $a->pname }}</div>
                                    @if ($a->subtitle)<div class="text-[11px] text-ink-500">{{ $a->subtitle }}</div>@endif
                                </td>
                                <td class="px-2 py-3 text-right font-mono">{{ \App\Support\FormatSettings::currency($a->chargeableAmount()) }}</td>
                                <td class="px-2 py-3 text-ink-600">{{ $a->lifetime ? __('one-time') : (($a->plan_duration ?: 1) . ' ' . ($a->plan_unit ?: 'month')) }}</td>
                                <td class="px-2 py-3">
                                    <div class="flex flex-wrap gap-1 max-w-[280px]">
                                        @forelse (array_slice($grants, 0, 4) as $g)
                                            <span class="px-1.5 py-0.5 rounded bg-wa-mint text-wa-deep text-[10px] font-mono">{{ $g }}</span>
                                        @empty
                                            <span class="text-[11px] text-ink-400">{{ __('nothing set') }}</span>
                                        @endforelse
                                        @if (count($grants) > 4)<span class="text-[10px] text-ink-500">+{{ count($grants) - 4 }}</span>@endif
                                    </div>
                                </td>
                                <td class="px-2 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-mono uppercase {{ $a->status ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $a->status ? 'bg-wa-green' : 'bg-ink-300' }}"></span>
                                        {{ $a->status ? __('Active') : __('Off') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.addons.edit', $a->id) }}"
                                            class="px-2.5 py-1.5 rounded-lg border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('admin.addons.toggle', $a->id) }}">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium">{{ $a->status ? __('Disable') : __('Enable') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.addons.destroy', $a->id) }}"
                                            data-confirm="{{ __('Delete this add-on? Workspaces that bought it keep it until it expires.') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[11.5px] font-medium">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-ink-500">
                                    <div class="text-[13px]">{{ __('No add-ons yet.') }}</div>
                                    <a href="{{ route('admin.addons.create') }}" class="text-wa-deep font-semibold hover:underline text-[12.5px]">{{ __('Create your first add-on') }}</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</x-layouts.admin>

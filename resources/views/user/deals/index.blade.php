<x-layouts.user :title="__('Sales Pipeline')" nav-key="deals" page="user-deals-board">

{{--
    Sales Pipeline — server-rendered Kanban board.

    Columns = pipeline stages, cards = deals. Drag a card to another column →
    PATCH /deals/{id}/stage (user-deals-board.js). Per-column totals + a
    weighted forecast (value × stage probability) sit in the header.
    Contact phone numbers are always masked (mask_phone()).
--}}


<div class="max-w-[1600px] mx-auto px-4 sm:px-7 py-6">

    @php
        // Currency list + symbols are DYNAMIC from the admin's active currencies
        // (Admin → Currencies) so e.g. IDR shows up for Indonesian workspaces
        // instead of a hard-coded INR/USD/EUR/GBP/AED list. Falls back to a sane
        // set if the table is empty.
        // 100% dynamic from the admin Currencies table — no hard-coded list. Add a
        // currency in Admin → Currencies and it appears here (e.g. IDR).
        $dealCurrencies = \App\Models\Currency::active()->orderBy('code')->pluck('code')->map(fn($c) => strtoupper($c))->all();
        // Keep the pipeline's own stored currency selectable even if it was later
        // deactivated in admin (so existing deals still render).
        if (!empty($pipeline->currency) && !in_array(strtoupper($pipeline->currency), $dealCurrencies, true)) {
            array_unshift($dealCurrencies, strtoupper($pipeline->currency));
        }
        $dealSymMap = \App\Models\Currency::pluck('symbol', 'code')->mapWithKeys(fn($s, $c) => [strtoupper($c) => $s])->all();
        // Use the dynamic display currency the controller resolved (workspace
        // setting → platform default), not the pipeline's stored code.
        $sym = $dealSymMap[$currency] ?? ($currency.' ');
        // Deterministic avatar palette + source-pill map (decorative).
        $palette = ['bg-accent-coral','bg-wa-teal','bg-accent-amber','bg-wa-deep','bg-accent-plum','bg-accent-sky'];
        $srcMap = [
            'order'   => ['label'=>'Order',      'pill'=>'bg-wa-bubble text-wa-deep',          'icon'=>'bg-wa-green'],
            'inbox'   => ['label'=>'WhatsApp',   'pill'=>'bg-wa-bubble text-wa-deep',          'icon'=>'bg-wa-green'],
            'shopify' => ['label'=>'Shopify',    'pill'=>'bg-accent-plum/15 text-accent-plum', 'icon'=>'bg-accent-plum'],
            'woo'     => ['label'=>'WooCommerce','pill'=>'bg-accent-sky/15 text-accent-sky',   'icon'=>'bg-accent-sky'],
            'form'    => ['label'=>'Form',       'pill'=>'bg-accent-amber/20 text-[#8B5A14]',  'icon'=>'bg-accent-amber'],
            'api'     => ['label'=>'API',        'pill'=>'bg-paper-100 text-ink-600',          'icon'=>'bg-ink-500'],
            'manual'  => ['label'=>'Manual',     'pill'=>'bg-paper-100 text-ink-600',          'icon'=>'bg-ink-400'],
        ];
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <div class="flex items-center gap-2.5 mb-1.5 text-[11px] font-mono uppercase tracking-[0.18em] text-ink-500">
                <span>{{ __('Sales') }}</span>
                <span class="w-1 h-1 rounded-full bg-ink-400"></span>
                <span class="inline-flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-wa-green dl-pulse"></span>{{ __('Pipeline of opportunities') }}</span>
            </div>
            <h1 class="font-serif text-[40px] leading-none text-ink-900">{{ __('Deals') }} <span class="italic text-wa-deep">{{ __('pipeline') }}</span></h1>
        </div>
        <div class="flex items-center gap-2">
            @if($pipelines->count() > 1)
                <select onchange="window.location='{{ route('user.deals.index') }}?pipeline='+this.value"
                        class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 text-[12px] font-medium hover:bg-paper-50 cursor-pointer">
                    @foreach($pipelines as $p)
                        <option value="{{ $p->id }}" @selected($p->id === $pipeline->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            @endif
            <a href="{{ route('user.deals.reports') }}" class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 text-[12px] font-medium hover:bg-paper-50 inline-flex items-center gap-1.5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3v18h18M7 14l4-4 3 3 5-6"/></svg>{{ __('Reports') }}
            </a>
            <button type="button" data-deal-settings aria-label="{{ __('Settings') }}" class="w-9 h-9 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 grid place-items-center text-ink-600">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H1a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 3 8.6a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V1a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.14.36.43.66.79.86"/></svg>
            </button>
            <button type="button" data-deal-new class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('New deal') }}
            </button>
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <div class="dl-kpi"><div class="l">{{ __('Open pipeline') }}</div><div class="v">{{ $kpis['open_value'] }}</div><div class="text-[10px] text-ink-500 mt-1">{{ $kpis['open_count'] }} {{ __('deals') }}</div></div>
        <div class="dl-kpi"><div class="l">{{ __('Weighted forecast') }}</div><div class="v is-accent">{{ $kpis['forecast'] }}</div><div class="text-[10px] text-ink-500 mt-1">{{ __('by probability') }}</div></div>
        <div class="dl-kpi"><div class="l">{{ __('Won · this month') }}</div><div class="v">{{ $kpis['won_value'] }}</div><div class="text-[10px] text-ink-500 mt-1">{{ $kpis['won_this_month'] }} {{ __('deals') }}</div></div>
        <div class="dl-kpi"><div class="l">{{ __('Win rate') }}</div><div class="v">{{ $kpis['win_rate'] }}%</div><div class="text-[10px] text-ink-500 mt-1">{{ __('won vs lost') }}</div></div>
        <div class="dl-kpi"><div class="l">{{ __('Open deals') }}</div><div class="v">{{ $kpis['open_count'] }}</div><div class="text-[10px] text-ink-500 mt-1">{{ __('in pipeline') }}</div></div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('user.deals.index') }}" class="flex flex-wrap items-center gap-2 mb-4">
        <input type="hidden" name="pipeline" value="{{ $pipeline->id }}">
        <div class="flex items-center gap-2 border border-paper-200 rounded-full px-3 py-1.5 bg-paper-0 w-56">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search deals…') }}" class="bg-transparent outline-none text-xs flex-1 placeholder:text-ink-500">
        </div>
        <select name="owner" class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 text-xs">
            <option value="">{{ __('All owners') }}</option>
            @foreach($members as $m)
                <option value="{{ $m->id }}" @selected((int)$filters['owner'] === (int)$m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
        <select name="source" class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 text-xs">
            <option value="">{{ __('All sources') }}</option>
            @foreach($sources as $s)
                <option value="{{ $s }}" @selected($filters['source'] === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-1.5 rounded-full border border-paper-200 bg-paper-0 text-xs font-medium hover:bg-paper-50">{{ __('Filter') }}</button>
        @if($filters['q'] || $filters['owner'] || $filters['source'])
            <a href="{{ route('user.deals.index') }}?pipeline={{ $pipeline->id }}" class="text-xs text-ink-500 underline">{{ __('Clear') }}</a>
        @endif
    </form>

    {{-- Board --}}
    <div class="dl-board" id="dl-board" data-stage-url="{{ url('/deals') }}" data-csrf="{{ csrf_token() }}">
        @foreach($columns as $col)
            @php $stage = $col['stage']; $colCls = $stage->is_won ? 'dl-col-won' : ($stage->is_lost ? 'dl-col-lost' : ''); @endphp
            <div class="dl-col {{ $colCls }}" data-stage-id="{{ $stage->id }}">
                <div class="dl-col-head">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="dl-dot" style="background: {{ $stage->color }}"></span>
                        <span class="text-[13px] font-semibold text-ink-900 truncate">{{ $stage->name }}</span>
                        <span class="dl-count" data-count>{{ $col['count'] }}</span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <span class="text-[11px] font-mono {{ $stage->is_won ? 'text-wa-deep font-semibold' : 'text-ink-500' }}">{{ $sym }}{{ number_format($col['value_minor']/100, 0) }}</span>
                        <button type="button" data-deal-new aria-label="{{ __('Add deal') }}" class="w-6 h-6 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500">
                            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>
                        </button>
                    </div>
                </div>
                <div class="dl-col-body" data-drop>
                    @forelse($col['deals'] as $deal)
                        @php
                            $c = $deal->contact;
                            $who = $c && $c->id
                                ? ($c->name ?: trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: mask_phone((string)($c->country_code.$c->mobile)))
                                : null;
                            $src = $srcMap[$deal->source] ?? $srcMap['manual'];
                            $ownerName = $deal->owner && $deal->owner->id ? $deal->owner->name : ($who ?: 'Deal');
                            $initials = collect(preg_split('/\s+/', trim($ownerName)))->filter()->take(2)->map(fn($w)=>mb_substr($w,0,1))->implode('');
                            $avColor = $palette[abs(crc32((string)$ownerName)) % count($palette)];
                            $prob = (int) $stage->probability;
                            $showProg = !$stage->is_won && !$stage->is_lost && $prob > 0 && $prob < 100;
                            $age = $deal->created_at ? $deal->created_at->diffForHumans(['short'=>true,'parts'=>1,'syntax'=>\Carbon\CarbonInterface::DIFF_ABSOLUTE]) : '';
                        @endphp
                        <div class="dl-card {{ $stage->is_won ? 'dl-card-won' : '' }}" draggable="true" data-deal-id="{{ $deal->id }}">
                            <div class="flex items-start justify-between mb-2">
                                @if($stage->is_won)
                                    <span class="dl-pill bg-wa-green text-ink-900"><svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 6l3 3 5-6"/></svg>{{ __('Closed won') }}</span>
                                @elseif($stage->is_lost)
                                    <span class="dl-pill" style="background:#FDE3E3;color:#B91C1C">{{ __('Lost') }}</span>
                                @else
                                    <span class="dl-pill {{ $src['pill'] }}"><span class="dl-srcicon {{ $src['icon'] }}"><svg viewBox="0 0 12 12" class="w-2 h-2" fill="currentColor"><path d="M2 3l8-1v8l-8-1z"/></svg></span>{{ $src['label'] }}</span>
                                @endif
                                <span class="font-mono text-[10px] text-ink-400 shrink-0 ml-2">{{ $age }}</span>
                            </div>
                            <div class="text-[13.5px] font-semibold leading-snug text-ink-900" data-card-title>{{ $deal->title }}</div>
                            @if($who)
                                <div class="text-[11px] text-ink-500 mt-0.5 truncate">{{ $who }}</div>
                            @endif
                            @if($showProg)
                                <div class="mt-2.5"><div class="dl-progress"><span style="width: {{ $prob }}%; background: {{ $prob >= 75 ? 'var(--color-wa-green)' : ($prob >= 50 ? 'var(--color-accent-amber)' : 'var(--color-wa-teal)') }}"></span></div></div>
                            @endif
                            <div class="flex items-center justify-between mt-2.5">
                                {{-- Converted to the workspace display currency so the card
                                     matches the column total + KPIs (no $ total over ₹ cards). --}}
                                <span class="dl-amount">{{ $sym }}{{ number_format(($deal->display_minor ?? 0) / 100, 2) }}</span>
                                <div class="flex items-center gap-1.5">
                                    @if($showProg)<span class="font-mono text-[9px] text-ink-500">{{ $prob }}%</span>@endif
                                    <span class="dl-avatar {{ $avColor }}">{{ mb_strtoupper($initials ?: '–') }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[12px] text-ink-400 text-center py-8" data-empty>{{ __('No deals yet') }}</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- New-deal modal --}}
<div class="dl-modal-backdrop" id="dl-new-modal">
    <div class="dl-modal">
        <h3 class="text-lg font-bold text-ink-900 mb-4">{{ __('New deal') }}</h3>
        <form id="dl-new-form" class="space-y-3">
            <input type="hidden" name="pipeline_id" value="{{ $pipeline->id }}">
            <div>
                <label class="block text-xs font-semibold text-ink-500 mb-1">{{ __('Title') }}</label>
                <input type="text" name="title" required maxlength="191" class="dl-input w-full" placeholder="{{ __('e.g. Acme Corp — annual plan') }}">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-ink-500 mb-1">{{ __('Value') }}</label>
                    <input type="number" name="value" min="0" step="0.01" class="dl-input w-full" placeholder="0">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-500 mb-1">{{ __('Currency') }}</label>
                    <select name="currency" class="dl-input w-full">
                        @foreach($dealCurrencies as $cur)
                            <option value="{{ $cur }}" @selected($cur === strtoupper($pipeline->currency ?? ''))>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-ink-500 mb-1">{{ __('Stage') }}</label>
                    <select name="stage_id" class="dl-input w-full">
                        @foreach($columns as $col)
                            <option value="{{ $col['stage']->id }}">{{ $col['stage']->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-500 mb-1">{{ __('Owner') }}</label>
                    <select name="owner_user_id" class="dl-input w-full">
                        <option value="">{{ __('Me') }}</option>
                        @foreach($members as $m)
                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-500 mb-1">{{ __('Expected close date') }}</label>
                <input type="date" name="expected_close_date" class="dl-input w-full">
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" class="dl-btn dl-btn-ghost" data-deal-cancel>{{ __('Cancel') }}</button>
                <button type="submit" class="dl-btn dl-btn-primary">{{ __('Create deal') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Pipeline settings (auto-deal from orders) --}}
<div class="dl-modal-backdrop" id="dl-settings-modal" data-url="{{ route('user.deals.settings') }}">
    <div class="dl-modal">
        <h3 class="text-lg font-bold text-ink-900 mb-1">{{ __('Pipeline settings') }}</h3>
        <p class="text-[12px] text-ink-500 mb-4">{{ __('Turn new orders into deals automatically.') }}</p>
        <form id="dl-settings-form" class="space-y-4">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="auto_from_orders" value="1" @checked($wsSettings['auto']) class="mt-1">
                <span>
                    <span class="block text-[13px] font-semibold text-ink-900">{{ __('Auto-create a deal from new orders') }}</span>
                    <span class="block text-[12px] text-ink-500">{{ __('Each new order lands in the default pipeline as an open deal.') }}</span>
                </span>
            </label>
            <div>
                <label class="block text-xs font-semibold text-ink-500 mb-1">{{ __('Only for orders above (blank = any value)') }}</label>
                <input type="number" name="min_value" min="0" step="0.01" value="{{ $wsSettings['min'] }}" class="dl-field" placeholder="0">
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" class="dl-btn dl-btn-ghost" data-settings-cancel>{{ __('Cancel') }}</button>
                <button type="submit" class="dl-btn dl-btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Deal detail slide-over (body rendered by user-deals-board.js from /deals/{id}) --}}
<div class="dl-panel-backdrop" id="dl-panel" data-base="{{ url('/deals') }}" data-chat="{{ url('/chat') }}">
    <div class="dl-panel">
        <div data-panel-body>
            <div class="text-center text-ink-400 py-20 text-sm">{{ __('Loading…') }}</div>
        </div>
    </div>
</div>

</x-layouts.user>

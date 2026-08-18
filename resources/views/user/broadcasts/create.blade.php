@php
    // Pre-compute defaults so the form opens with sensible values:
    // send-date = today in the workspace tz, send-time = now + 30min.
    // The schedule fields stay hidden until the operator clicks
    // "Schedule for later" — JS toggles `[data-sched]` visibility.
    $tz = $defaultTz ?? config('app.timezone', 'UTC');
    $defaultDate = \Illuminate\Support\Carbon::now($tz)->format('Y-m-d');
    $defaultTime = \Illuminate\Support\Carbon::now($tz)->addMinutes(30)->format('H:i');
    // Group categories of WaTemplate buckets so the preview can show
    // the right Marketing/Utility chip on the right.
    $templates = $templates ?? collect();
    $devices = $devices ?? collect();
    $contacts = $contacts ?? collect();
    $groups = $groups ?? collect();
    $timezones = $timezones ?? [config('app.timezone', 'UTC')];
@endphp

<x-layouts.user :title="__('New Broadcast')" nav-key="more" page="user-broadcasts-create">

    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/broadcasts') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to Broadcasts') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Broadcasts / New') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Create new') }} <span
                            class="italic text-wa-deep">{{ __('broadcast') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ __('Draft / unsaved') }}</span>
                <button type="submit" form="broadcastForm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M2 8l5 5 7-9" />
                    </svg>
                    Add Broadcast
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        @if (session('error'))
            <div
                class="mb-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12.5px] text-[#A1431F]">
                {{ session('error') }}</div>
        @endif
        @if (isset($errors) && $errors->any())
            <div
                class="mb-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12.5px] text-[#A1431F]">
                {{ $errors->first() }}</div>
        @endif

        <form id="broadcastForm" method="POST" action="{{ route('user.broadcasts.store') }}"
            class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start">
            @csrf
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-0 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Broadcast setup') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('template message') }}</span>
                    </div>
                </div>

                <div class="p-5 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                for="broadcastName">{{ __('Broadcast name') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="broadcastName" name="broadcast_name" type="text"
                                value="{{ old('broadcast_name') }}"
                                placeholder="{{ __('e.g. May offer template send') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Internal label used in broadcast reports.') }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                for="templateSelect">{{ __('Select template message') }} <span
                                    class="text-accent-coral">*</span></label>
                            @php
                                // Multi-engine: show the Twilio "ready / plain-text" template hints
                                // whenever Twilio is among the workspace's enabled engines, not only
                                // when it's the single default (mirrors TemplatesController).
                                $_bcWsId = auth()->user()->current_workspace_id ?? 0;
                                $isTwilioWs = $_bcWsId
                                    && \App\Services\WorkspaceEngine::isEngineEnabled($_bcWsId, 'twilio');
                            @endphp
                            <select id="templateSelect" name="template_id"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                <option value="">— Pick an approved template —</option>
                                @foreach ($templates as $t)
                                    @php
                                        $hasContentSid = !empty($t->twilio_content_sid);
                                        // For Twilio workspaces, flag templates that lack a
                                        // ContentSid — they'll silently degrade to plain Body
// text. The operator needs to see this BEFORE submit so
// marketing/utility/auth sends don't get downgraded.
                                        $degradeMark = $isTwilioWs && !$hasContentSid ? ' · plain text only' : '';
                                        $sidMark = $isTwilioWs && $hasContentSid ? ' ✓ Twilio ready' : '';
                                    @endphp
                                    <option value="{{ $t->id }}" data-body="{{ (string) $t->template_body }}"
                                        data-header="{{ (string) ($t->header ?? '') }}"
                                        data-footer="{{ (string) ($t->footer ?? '') }}"
                                        data-category="{{ (string) ($t->meta_category ?? ($t->category ?? 'utility')) }}"
                                        data-template-type="{{ (string) ($t->template_type ?? 'standard') }}"
                                        data-provider-config="{{ $t->provider_config_id }}"
                                        data-twilio-content-sid="{{ $hasContentSid ? '1' : '0' }}"
                                        data-buttons='@json($t->buttons ?? [])'
                                        data-attachment-type="{{ (string) ($t->attachment_type ?? '') }}"
                                        data-attachment-url="{{ $t->attachment_file ? media_url($t->attachment_file) : '' }}"
                                        data-variable-map='@json($t->variable_map ?? [])' @selected(old('template_id') == $t->id)>
                                        {{ $t->template_name }}@if ($t->provider && method_exists($t, 'engineKey') && $t->engineKey() === 'waba') · {{ $t->provider->display_label ?: $t->provider->phone_number }}@endif{{ $sidMark }}{{ $degradeMark }}</option>
                                @endforeach
                            </select>

                            {{-- Send-time mapping: edit this broadcast's variable
 values, header media, and button links WITHOUT editing the
 template. Posts `template_overrides`. --}}
                            <x-template-live-mapping :templates="$templates" target="#templateSelect" />

                            @if ($templates->isEmpty())
                                <div class="text-[10.5px] text-accent-coral mt-1">
                                    {{ __('No approved templates yet.') }} <a href="{{ url('/templates/create') }}"
                                        class="font-semibold hover:underline">{{ __('Create one →') }}</a></div>
                            @elseif ($isTwilioWs)
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Twilio compliant sends require a Content SID per template — flag a template as "Twilio ready" via /templates/{id}/edit. Templates without a ContentSid will send as plain text (loses Meta template approval).') }}
                                </div>
                            @else
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Broadcasts send approved templates only.') }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Device picker — multi-device aware. With one device we
 render a single hidden input. With 2+ we render a
 checkbox list and the controller fans out into N
 broadcasts at save time. data-device-picker = refreshed in place
 by the global Connect-device popover after a new device connects. --}}
                    <div data-device-picker>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5">
                            <span>Send from device{{ $devices->count() > 1 ? 's' : '' }} <span
                                    class="text-accent-coral">*</span></span>
                            @if ($devices->count() > 1)
                                <span
                                    class="font-mono text-[10px] text-wa-deep normal-case tracking-normal">{{ __('Multi-device — tick all that apply') }}</span>
                            @endif
                        </label>
                        @if ($devices->count() === 0)
                            <div
                                class="text-[11px] text-accent-coral border border-accent-coral/30 rounded-lg px-3 py-2 bg-accent-coral/5 flex items-center gap-2 flex-wrap">
                                {{ __('No connected devices.') }}
                                <button type="button" data-connect-device class="font-semibold text-wa-deep hover:underline cursor-pointer">{{ __('Connect one →') }}</button>
                            </div>
                        @elseif ($devices->count() === 1)
                            @php $only = $devices->first(); @endphp
                            <div
                                class="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-paper-200 bg-paper-50/60">
                                {{-- Single sender: post the composite engine:id key so the
                                     store stamps the chosen engine (legacy bare-int still
                                     accepted as a fallback). --}}
                                <input type="hidden" name="device_ids[]" value="{{ $only['key'] }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <rect x="4.5" y="2" width="7" height="12" rx="1.5" />
                                    <path d="M7 12.5h2" />
                                </svg>
                                <span class="text-[12.5px] font-medium">{{ $only['label'] }}</span>
                                <span class="ml-auto text-[10.5px] font-mono text-ink-500">{{ $only['phone'] }}</span>
                            </div>
                        @else
                            {{-- Multi-device picker. Operator ticks devices and (when
 2+ are ticked) sets per-device share weights so they
 control HOW the audience is split. Weights are
 positive numbers; the server normalises them so an
 audience of N contacts gets `round(N * weight_i / sum_weights)`
 per device. Equal weights = even split (legacy
 round-robin). 70 / 30 = bigger device takes 70% of
 the audience. --}}
                            @php
                                // Group senders by engine so a workspace running 2+ engines
                                // at once shows a header per engine ("Unofficial API", "Meta",
                                // "Twilio"). One engine ⇒ no headers, identical to the legacy
                                // flat list. Preserve the senders() order (default first).
                                $bcByEngine    = $devices->groupBy('engine');
                                $bcEngineOrder = $devices->pluck('engine')->unique()->values();
                                $bcMultiEngine = $bcEngineOrder->count() > 1;
                                $bcEngineLabel = fn ($engine) => optional($devices->firstWhere('engine', $engine))['engineLabel'] ?? $engine;
                            @endphp
                            <div id="broadcastDeviceList"
                                class="rounded-lg border border-paper-200 bg-white max-h-56 overflow-y-auto">
                                @foreach ($bcEngineOrder as $bcEngine)
                                    @if ($bcMultiEngine)
                                        <div
                                            class="px-3 py-1.5 bg-paper-50 border-b border-paper-100 flex items-center gap-2 sticky top-0 z-10">
                                            <span
                                                class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">{{ $bcEngineLabel($bcEngine) }}</span>
                                        </div>
                                    @endif
                                    @foreach ($bcByEngine[$bcEngine] as $d)
                                        <label
                                            class="device-row flex items-center gap-2.5 px-3 py-2 text-[12.5px] cursor-pointer hover:bg-paper-50 border-b border-paper-100 last:border-b-0 has-[:checked]:bg-wa-mint/40"
                                            data-device-id="{{ $d['key'] }}">
                                            <input type="checkbox" name="device_ids[]" value="{{ $d['key'] }}"
                                                class="device-cb w-4 h-4 rounded accent-wa-deep shrink-0">
                                            <span class="flex-1 min-w-0">
                                                <span class="block truncate">{{ $d['label'] }}</span>
                                                <span
                                                    class="block font-mono text-[10px] text-ink-500 truncate">{{ $d['phone'] }}</span>
                                            </span>
                                            <span
                                                class="device-share-wrap flex items-center gap-1 shrink-0 opacity-50 pointer-events-none transition">
                                                <span
                                                    class="font-mono text-[10px] uppercase tracking-wider text-ink-500">{{ __('share') }}</span>
                                                <input type="number" name="device_share[{{ $d['key'] }}]"
                                                    value="1" min="0" step="1"
                                                    class="device-share w-14 px-2 py-1 border border-paper-200 rounded text-[12px] font-mono text-right focus:outline-none focus:border-wa-deep">
                                                <span
                                                    class="device-share-pct font-mono text-[10px] text-wa-deep w-10 text-right">—</span>
                                            </span>
                                        </label>
                                    @endforeach
                                @endforeach
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Tick devices and set share weights — e.g.') }} <span class="font-mono">7</span>
                                / <span class="font-mono">3</span> sends 70% of contacts via the first device and 30%
                                via the second. Equal weights split evenly.</div>
                        @endif
                    </div>

                    <div class="border-t border-paper-200 pt-5">
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <div>
                                <h2 class="font-serif text-[20px] leading-tight">
                                    {{ __('Who do you want to send it to?') }}</h2>
                                <p class="text-[12px] text-ink-500 mt-1">
                                    {{ __('Pick contacts directly or pick a contact group.') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="selectedSummary" class="font-mono text-[10.5px] text-ink-500">0
                                    selected</span>
                            </div>
                        </div>

                        {{-- Tag audience — ticking a tag adds every contact carrying
 it, merged with groups / individual picks / manual numbers.
 Same resolution + workspace guard as the campaigns page. --}}
                        @if (!empty($tags) && count($tags))
                            <div class="mb-3 flex flex-wrap gap-2">
                                <span
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mr-1 self-center">{{ __('Tags') }}</span>
                                @foreach ($tags as $tg)
                                    <label
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 cursor-pointer text-[11.5px] has-[:checked]:bg-wa-mint/40 has-[:checked]:border-wa-deep">
                                        <input type="checkbox" name="tags[]" value="{{ $tg->id }}" class="accent-wa-deep"
                                            @checked(in_array((string) $tg->id, (array) old('tags', []), true))>
                                        <span class="w-[7px] h-[7px] rounded-full shrink-0"
                                            style="background: {{ $tg->color ?: 'var(--color-wa-deep)' }}"></span>
                                        <span>{{ $tg->name }}</span>
                                        <span
                                            class="font-mono text-[10px] text-ink-500">{{ $tagCounts[$tg->id] ?? 0 }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        @if ($groups->isNotEmpty())
                            <div class="mb-3 flex flex-wrap gap-2">
                                <span
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mr-1 self-center">{{ __('Groups') }}</span>
                                @foreach ($groups as $g)
                                    <label
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 cursor-pointer text-[11.5px] has-[:checked]:bg-wa-mint/40 has-[:checked]:border-wa-deep">
                                        <input type="checkbox" name="groups[]" value="{{ $g->id }}"
                                            class="w-3 h-3 rounded accent-wa-deep">
                                        <span>{{ $g->user_group ?? 'Group #' . $g->id }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                        <div class="relative mb-3">
                            <svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                fill="none" stroke="currentColor" stroke-width="1.6">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input id="contactSearch" type="text"
                                class="w-full pl-9 pr-3 py-2 rounded-lg border border-paper-200 bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Search by name or phone') }}">
                        </div>

                        <div class="border border-paper-200 rounded-xl overflow-hidden bg-white">
                            <div class="max-h-[320px] overflow-y-auto">
                                <table class="w-full text-[12.5px] table-fixed">
                                    <thead class="bg-paper-50 border-b border-paper-200 text-ink-500 sticky top-0">
                                        <tr>
                                            <th class="text-left px-4 py-2.5 w-[48px]"><input id="selectAllContacts"
                                                    type="checkbox" class="w-4 h-4 accent-wa-deep"></th>
                                            <th
                                                class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                                {{ __('Name') }}</th>
                                            <th
                                                class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5 w-[200px]">
                                                {{ __('Phone') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-paper-200" id="contactsTbody">
                                        @forelse ($contacts as $c)
                                            @php
                                                // Build display name from whatever fields exist —
                                                // `name` is the catch-all; some imports only have
                                                // first/last. Phone uses `mobile` + optional
                                                // `country_code` (legacy schema kept them split).
                                                $cname =
                                                    trim((string) ($c->name ?? '')) ?:
                                                    trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?:
                                                    'Contact #' . $c->id;
                                                $cdial = trim((string) ($c->country_code ?? ''));
                                                $cphone = trim((string) ($c->mobile ?? ''));
                                                $cfull =
                                                    $cphone === ''
                                                        ? ''
                                                        : ($cdial !== ''
                                                            ? '+' . ltrim($cdial, '+') . ' ' . $cphone
                                                            : $cphone);
                                            @endphp
                                            <tr data-search="{{ mb_strtolower($cname . ' ' . $cfull) }}">
                                                <td class="px-4 py-2.5"><input data-contact type="checkbox"
                                                        name="contacts[]" value="{{ $c->id }}"
                                                        class="w-4 h-4 accent-wa-deep"></td>
                                                <td class="px-3 py-2.5">
                                                    <div class="font-semibold truncate">{{ $cname }}</div>
                                                </td>
                                                <td class="px-3 py-2.5 font-mono text-[11px]">{{ $cfull ?: '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3"
                                                    class="px-4 py-6 text-center text-[12px] text-ink-500">
                                                    {{ __('No contacts yet.') }} <a href="{{ url('/contacts') }}"
                                                        class="font-semibold hover:underline text-wa-deep">{{ __('Add some →') }}</a>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-paper-200 pt-5">
                        <h2 class="font-serif text-[20px] leading-tight">
                            {{ __('When do you want to send your broadcast?') }}</h2>
                        <p class="text-[12px] text-ink-500 mt-1 mb-3">
                            {{ __('Send immediately or pick a future date / time.') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4" id="scheduleCards">
                            <label
                                class="schedule-card border border-wa-deep bg-wa-bubble/50 rounded-2xl p-4 cursor-pointer"
                                data-card="now">
                                <input class="sr-only" type="radio" name="schedule_type" value="now" checked>
                                <div class="font-serif text-[20px] leading-tight">{{ __('Send now') }}</div>
                                <p class="text-[12px] text-ink-500 mt-1.5">{{ __('Send after final validation.') }}
                                </p>
                            </label>
                            <label
                                class="schedule-card border border-paper-200 bg-white rounded-2xl p-4 cursor-pointer hover:bg-paper-50"
                                data-card="later">
                                <input class="sr-only" type="radio" name="schedule_type" value="later">
                                <div class="font-serif text-[20px] leading-tight">{{ __('Schedule for later') }}</div>
                                <p class="text-[12px] text-ink-500 mt-1.5">{{ __('Choose a specific day and time.') }}
                                </p>
                            </label>
                        </div>
                        {{-- Schedule fields are hidden by default and revealed when
 "Schedule for later" is picked. Avoids the form looking
 cluttered for the common "send now" path. --}}
                        <div id="scheduleFields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="sendDate">{{ __('Send date') }}</label>
                                <input id="sendDate" name="send_date" type="date"
                                    value="{{ old('send_date', $defaultDate) }}" min="{{ $defaultDate }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="sendTime">{{ __('Send time') }}</label>
                                <input id="sendTime" name="send_time" type="time"
                                    value="{{ old('send_time', $defaultTime) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="timezone">{{ __('Timezone') }}</label>
                                <select id="timezone" name="timezone"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @foreach ($timezones as $tzOpt)
                                        <option value="{{ $tzOpt }}" @selected(old('timezone', $defaultTz) === $tzOpt)>
                                            {{ $tzOpt }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 sticky top-[92px]">
                    <div class="flex items-center justify-between mb-3">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Template preview') }}</span>
                        <span id="previewCategory"
                            class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep">—</span>
                    </div>
                    <div class="rounded-[24px] border border-ink-900/10 bg-ink-900 p-2 shadow-soft">
                        <div class="rounded-[19px] overflow-hidden bg-wa-chat">
                            <div class="h-11 bg-wa-deep text-paper-0 flex items-center gap-2 px-3">
                                <span
                                    class="w-7 h-7 rounded-full bg-paper-0 text-wa-deep grid place-items-center text-[11px] font-semibold">WA</span>
                                <div class="min-w-0">
                                    <div class="text-[12px] font-semibold truncate">{{ __('Broadcast preview') }}
                                    </div>
                                    <div class="text-[9.5px] text-paper-100 truncate">{{ __('Template message') }}
                                    </div>
                                </div>
                            </div>
                            <div
                                class="p-3 min-h-[270px] bg-[radial-gradient(circle_at_1px_1px,rgba(7,94,84,0.09)_1px,transparent_0)] bg-[length:18px_18px]">
                                <div
                                    class="ml-auto max-w-[255px] rounded-2xl rounded-tr-md bg-wa-bubble border border-wa-green/30 px-3 py-2 shadow-card">
                                    {{-- Media header — image attachments render an <img>; video/doc
 render a labelled chip. Hidden until the picked template
 carries an attachment_type. Mirrors the builder's #pp-attach. --}}
                                    <div id="previewMedia" class="rounded-[7px] overflow-hidden mb-2 hidden"></div>
                                    <div id="previewHeader" class="text-[12px] font-semibold text-ink-900 hidden">
                                    </div>
                                    <p id="previewBody"
                                        class="text-[12px] leading-relaxed text-ink-800 mt-1 whitespace-pre-wrap">
                                        {{ __('Pick a template to preview…') }}</p>
                                    <div id="previewFooter"
                                        class="text-[10px] text-ink-500 mt-2 pt-2 border-t border-wa-green/20 hidden">
                                    </div>
                                    <div id="previewTime" class="text-[10px] text-ink-500 text-right mt-1"></div>
                                </div>
                                {{-- Buttons area — each template button renders as a chip with the
 right glyph (link / phone / copy / reply), mirroring the
 builder's button chips. Sits under the bubble like WhatsApp. --}}
                                <div id="previewButtons"
                                    class="ml-auto max-w-[255px] flex flex-col gap-[3px] mt-[3px] hidden"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Selected') }}</div>
                            <div id="selectedCount" class="font-serif text-[22px] leading-tight mt-1">0</div>
                        </div>
                        <div class="rounded-lg border border-paper-200 bg-paper-50/60 p-3">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Schedule') }}</div>
                            <div id="scheduleLabel" class="font-serif text-[22px] leading-tight mt-1">
                                {{ __('Now') }}</div>
                        </div>
                    </div>
                </div>
            </aside>
        </form>
    </main>


</x-layouts.user>

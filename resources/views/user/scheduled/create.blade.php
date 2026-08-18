@php
    $devices = $devices ?? collect();
    $templates = $templates ?? collect();
    $groups = $groups ?? collect();
    $queues = $queues ?? collect();
@endphp

<x-layouts.user :title="__('Schedule Message')" nav-key="more" page="user-scheduled-create">

    <!-- Sticky toolbar -->
    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/scheduled') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Scheduled / New') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Schedule a') }} <span
                            class="italic text-wa-deep">{{ __('message') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ __('Draft / unsaved') }}</span>
                <button type="button"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        @php
            // Shape templates for the JS picker. Only approved+active templates are
            // surfaced to the operator — same rule the /templates page uses.
            $tplJson = $templates
                ->map(
                    fn($t) => [
                        'id' => $t->id,
                        'name' => $t->template_name,
                        'body' => $t->template_body ?: '',
                        'cat' => $t->category ?: 'Marketing',
                    ],
                )
                ->values()
                ->all();
        @endphp
        <form id="schedForm" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start"
            data-templates='@json($tplJson)'>

            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                <!-- Stepper -->
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40">
                    <div class="flex items-center" id="stepper">
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="1">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-wa-deep text-wa-deep ring-4 ring-wa-deep/10">1</span>
                            <span
                                class="lab text-[11.5px] font-semibold whitespace-nowrap text-wa-deep">{{ __('Basics') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="2">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">2</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Recipients') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="3">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">3</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Message') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="4">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">4</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('When') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 cursor-pointer" data-n="5">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">5</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Review') }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-5">

                    <!-- Step 1: Basics -->
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Schedule basics') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="sch-name">{{ __('Schedule name') }} <span
                                        class="text-accent-coral">*</span></label>
                                <input id="sch-name" name="schedule_name" type="text"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('e.g. Spring promo / VIP segment') }}" required>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __("Internal label only — recipients don't see this.") }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 flex items-center justify-between">
                                    <span>{{ __('Sender') }}{{ $senders->count() > 1 ? __('s') : '' }} <span
                                            class="text-accent-coral">*</span></span>
                                    @if ($senders->count() > 1)
                                        <span
                                            class="font-mono text-[10px] text-wa-deep normal-case tracking-normal">{{ __('Tick all that apply') }}</span>
                                    @endif
                                </label>
                                {{-- Unified multi-engine sender picker — surfaces every
 connected sender across ALL the workspace's enabled engines
 (Unofficial API / Meta / Twilio) as composite engine:id keys.
 Each ticked sender gets its own ScheduledMessage row (server
 fans out via sender[]). Contact-numbers split round-robin
 across rows so each customer gets one message total, not one
 per sender. A single connected sender renders as a one-item
 list which the JS auto-ticks for the legacy one-tap UX. --}}
                                <x-sender-picker :senders="$senders" name="sender" :multiple="true"
                                    :selected="old('sender', [])" />
                                @if ($senders->count() > 1)
                                    <div class="text-[10.5px] text-ink-500 mt-1">
                                        {{ __('Each ticked sender runs its own schedule with a fair share of the audience.') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Message type') }}
                                <span class="text-accent-coral">*</span></label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="msg-types">
                                <label
                                    class="msg-type cursor-pointer border rounded-xl p-3 transition border-wa-deep bg-[#F0F8F6]"
                                    data-type="plain">
                                    <span
                                        class="w-9 h-9 rounded-lg bg-wa-mint text-wa-deep grid place-items-center mb-2"><svg
                                            viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <path d="M3 4h10M3 8h10M3 12h7" />
                                        </svg></span>
                                    <div class="text-[13px] font-semibold">{{ __('Plain text') }}</div>
                                    <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                                        {{ __('Just words and emojis.') }}</p>
                                </label>
                                <label
                                    class="msg-type cursor-pointer border border-paper-200 bg-white rounded-xl p-3 transition hover:border-wa-deep"
                                    data-type="template">
                                    <span
                                        class="w-9 h-9 rounded-lg bg-wa-deep/10 text-wa-deep grid place-items-center mb-2"><svg
                                            viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <rect x="2.5" y="2.5" width="11" height="11" rx="1.5" />
                                            <path d="M2.5 6h11M6 13.5V6" />
                                        </svg></span>
                                    <div class="text-[13px] font-semibold">{{ __('Template') }}</div>
                                    <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                                        {{ __('Approved WhatsApp template.') }}</p>
                                </label>
                                <label
                                    class="msg-type cursor-pointer border border-paper-200 bg-white rounded-xl p-3 transition hover:border-wa-deep"
                                    data-type="media">
                                    <span
                                        class="w-9 h-9 rounded-lg bg-accent-amber/20 text-[#7B5A14] grid place-items-center mb-2"><svg
                                            viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                            <circle cx="6" cy="7" r="1.2" />
                                            <path d="m3 11 3-3 4 4 3-3 0 4" />
                                        </svg></span>
                                    <div class="text-[13px] font-semibold">{{ __('With media') }}</div>
                                    <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                                        {{ __('Image, video, doc, audio.') }}</p>
                                </label>
                                <label
                                    class="msg-type cursor-pointer border border-paper-200 bg-white rounded-xl p-3 transition hover:border-wa-deep"
                                    data-type="location">
                                    <span
                                        class="w-9 h-9 rounded-lg bg-[#D9E5F2] text-[#13478A] grid place-items-center mb-2"><svg
                                            viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <path d="M8 1a5 5 0 0 0-5 5c0 4 5 9 5 9s5-5 5-9a5 5 0 0 0-5-5z" />
                                            <circle cx="8" cy="6" r="2" />
                                        </svg></span>
                                    <div class="text-[13px] font-semibold">{{ __('Location') }}</div>
                                    <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">{{ __('Lat/long pin.') }}
                                    </p>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Recipients -->
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Recipients') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        {{-- Three recipient sources:
 Group — saved ContactGroup rows in this workspace
 Queue — re-target the recipients of a past completed
 broadcast (broadcasts → broadcast_contacts)
 Number — manual paste of phone numbers --}}
                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Recipient source') }}</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" id="rcp-types">
                                <label
                                    class="rcp-type cursor-pointer border rounded-xl p-3 transition border-wa-deep bg-[#F0F8F6]"
                                    data-rt="group">
                                    <div class="flex items-start justify-between mb-2">
                                        <span
                                            class="w-9 h-9 rounded-lg bg-wa-mint text-wa-deep grid place-items-center"><svg
                                                viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <circle cx="6" cy="6" r="3" />
                                                <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5" />
                                                <circle cx="11.5" cy="5.5" r="2" />
                                                <path d="M10.5 10c1.9.2 3.5 1.7 3.5 4" />
                                            </svg></span>
                                        <span
                                            class="rcp-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-100"><svg
                                                viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="2.4">
                                                <path d="M3 8l3 3 7-8" />
                                            </svg></span>
                                    </div>
                                    <div class="text-[13px] font-semibold">{{ __('Group') }}</div>
                                    <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                                        {{ __('Pick one or more saved contact groups.') }}</p>
                                </label>
                                <label
                                    class="rcp-type cursor-pointer border border-paper-200 bg-white rounded-xl p-3 transition hover:border-wa-deep"
                                    data-rt="tag">
                                    <div class="flex items-start justify-between mb-2">
                                        <span class="w-9 h-9 rounded-lg bg-wa-mint text-wa-deep grid place-items-center"><svg
                                                viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                                stroke-width="1.6">
                                                <path d="M2 7V2.6c0-.3.3-.6.6-.6H7l7 7-5 5-7-7z" />
                                                <circle cx="4.8" cy="4.8" r="1" />
                                            </svg></span>
                                        {{-- Selected tick — every .rcp-type tile must carry this
                                             (the switcher toggles it per card). --}}
                                        <span
                                            class="rcp-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0"><svg
                                                viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="2.4">
                                                <path d="M3 8l3 3 7-8" />
                                            </svg></span>
                                    </div>
                                    <div class="font-serif text-[15px] leading-tight">{{ __('Tags') }}</div>
                                    <p class="text-[11px] text-ink-500 mt-0.5">{{ __('Everyone carrying a tag') }}</p>
                                </label>
                                <label class="rcp-type cursor-pointer border rounded-xl p-3 transition border-paper-200 hover:bg-paper-50"
                                    data-rt="queue">
                                    <div class="flex items-start justify-between mb-2">
                                        <span
                                            class="w-9 h-9 rounded-lg bg-[#D9E5F2] text-[#13478A] grid place-items-center"><svg
                                                viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <rect x="2" y="3" width="12" height="3" rx="1" />
                                                <rect x="2" y="7" width="12" height="3" rx="1" />
                                                <rect x="2" y="11" width="12" height="3" rx="1" />
                                            </svg></span>
                                        <span
                                            class="rcp-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0"><svg
                                                viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="2.4">
                                                <path d="M3 8l3 3 7-8" />
                                            </svg></span>
                                    </div>
                                    <div class="text-[13px] font-semibold">{{ __('Audience queue') }}</div>
                                    <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                                        {{ __("Re-target a past broadcast's recipients.") }}</p>
                                </label>
                                <label
                                    class="rcp-type cursor-pointer border border-paper-200 bg-white rounded-xl p-3 transition hover:border-wa-deep"
                                    data-rt="number">
                                    <div class="flex items-start justify-between mb-2">
                                        <span
                                            class="w-9 h-9 rounded-lg bg-accent-amber/20 text-[#7B5A14] grid place-items-center"><svg
                                                viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                                                <circle cx="8" cy="11.5" r="0.8" />
                                            </svg></span>
                                        <span
                                            class="rcp-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0"><svg
                                                viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="2.4">
                                                <path d="M3 8l3 3 7-8" />
                                            </svg></span>
                                    </div>
                                    <div class="text-[13px] font-semibold">{{ __('Numbers') }}</div>
                                    <p class="mt-0.5 text-[11px] text-ink-500 leading-snug">
                                        {{ __('Paste a list manually.') }}</p>
                                </label>
                            </div>
                        </div>

                        {{-- Group panel — multi-select cards backed by real ContactGroup
 rows. Each card carries data-grp = the group's DB id, which
 the submit handler reads via `.q-chk:checked` after we wire a
 hidden checkbox per card below. --}}
                        <div class="rcp-panel" data-panel="group">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Choose group(s)') }}
                                <span class="text-[10.5px] font-normal text-ink-500">(tick any number)</span></label>
                            @if ($groups->isEmpty())
                                <div class="border border-dashed border-paper-200 rounded-lg p-6 text-center">
                                    <div class="text-[13px] text-ink-700 font-semibold mb-1">{{ __('No groups yet') }}
                                    </div>
                                    <div class="text-[11.5px] text-ink-500">{{ __('Create a group from') }} <a
                                            href="{{ url('/contacts') }}"
                                            class="text-wa-deep font-semibold hover:underline">{{ __('Contacts') }}</a>
                                        first, then come back.</div>
                                </div>
                            @else
                                <div class="grid grid-cols-2 gap-3" id="group-list">
                                    @foreach ($groups as $g)
                                        <label
                                            class="grp-card text-left border rounded-lg p-3 transition cursor-pointer border-paper-200 bg-white hover:border-wa-deep block"
                                            data-grp="{{ $g->id }}">
                                            <input type="checkbox" class="q-chk peer sr-only"
                                                data-id="{{ $g->id }}" />
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-[13px]">{{ $g->user_group }}</div>
                                                    <div class="font-mono text-[10.5px] text-ink-500 mt-0.5">group ·
                                                        #{{ $g->id }}</div>
                                                </div>
                                                <span
                                                    class="grp-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0 peer-checked:opacity-100"
                                                    @if ($g->color) style="background:{{ $g->color }}" @endif>
                                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                        stroke="currentColor" stroke-width="2.4">
                                                        <path d="M3 8l3 3 7-8" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Tag audience panel. Unlike groups/queues this is resolved
 AT FIRE TIME (NodeSchedulerClient), so a contact tagged after
 the schedule was created still receives it — which is what
 "message every VIP each Monday" has to mean. --}}
                        <div class="rcp-panel hidden" data-panel="tag">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Pick one or more tags') }}</label>
                            @if (empty($tags) || !count($tags))
                                <div class="border border-dashed border-paper-200 rounded-lg p-6 text-center">
                                    <div class="text-[13px] text-ink-700 font-semibold mb-1">{{ __('No tags yet') }}</div>
                                    <div class="text-[11.5px] text-ink-500">{{ __('Tag contacts from') }} <a
                                            href="{{ url('/contacts') }}"
                                            class="text-wa-deep font-semibold hover:underline">{{ __('Contacts') }}</a>
                                        {{ __('— then target them here.') }}</div>
                                </div>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($tags as $tg)
                                        <label
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 cursor-pointer text-[11.5px] has-[:checked]:bg-wa-mint/40 has-[:checked]:border-wa-deep">
                                            <input type="checkbox" name="target_tags[]" value="{{ $tg->id }}"
                                                class="tag-chk accent-wa-deep"
                                                data-count="{{ $tagCounts[$tg->id] ?? 0 }}">
                                            <span class="w-[7px] h-[7px] rounded-full shrink-0"
                                                style="background: {{ $tg->color ?: 'var(--color-wa-deep)' }}"></span>
                                            <span>{{ $tg->name }}</span>
                                            <span
                                                class="font-mono text-[10px] text-ink-500">{{ $tagCounts[$tg->id] ?? 0 }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="text-[10.5px] text-ink-500 mt-2">
                                    {{ __('Recipients are recalculated when the schedule fires, so newly tagged contacts are included.') }}
                                </p>
                            @endif
                        </div>

                        {{-- Audience queue panel — dynamic. Each row = one past broadcast.
 The hidden checkbox carries data-id (broadcasts.id) and
 data-count (total_recipients), which `refreshRecipientTotal`
 reads to show the rolling sum. --}}
                        <div class="rcp-panel hidden" data-panel="queue">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Pick one or more past broadcasts') }}</label>
                            @if ($queues->isEmpty())
                                <div class="border border-dashed border-paper-200 rounded-lg p-6 text-center">
                                    <div class="text-[13px] text-ink-700 font-semibold mb-1">
                                        {{ __('No completed broadcasts yet') }}</div>
                                    <div class="text-[11.5px] text-ink-500">{{ __('Send a') }} <a
                                            href="{{ url('/broadcasts') }}"
                                            class="text-wa-deep font-semibold hover:underline">{{ __('broadcast') }}</a>
                                        first — finished sends will appear here so you can re-target their recipients.
                                    </div>
                                </div>
                            @else
                                <div class="border border-paper-200 rounded-lg bg-white divide-y divide-paper-200"
                                    id="queue-list">
                                    @foreach ($queues as $q)
                                        <label
                                            class="q-row flex items-center gap-3 px-3 py-2.5 cursor-pointer hover:bg-paper-50">
                                            <input type="checkbox" class="q-chk peer sr-only"
                                                data-id="{{ $q->id }}"
                                                data-count="{{ $q->total_recipients ?? 0 }}" />
                                            <span
                                                class="w-5 h-5 rounded border-[1.5px] border-paper-200 grid place-items-center peer-checked:bg-wa-deep peer-checked:border-wa-deep peer-checked:[&>svg]:opacity-100 transition shrink-0">
                                                <svg viewBox="0 0 16 16"
                                                    class="w-3 h-3 text-paper-0 opacity-0 transition" fill="none"
                                                    stroke="currentColor" stroke-width="2.4">
                                                    <path d="M3 8l3 3 7-8" />
                                                </svg>
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[13px] font-semibold truncate">
                                                    {{ $q->name ?: 'Broadcast #' . $q->id }}</div>
                                                <div class="text-[10.5px] text-ink-500 font-mono">
                                                    {{ $q->status }}
                                                    @if ($q->completed_at)
                                                        ·
                                                        {{ \Illuminate\Support\Carbon::parse($q->completed_at)->diffForHumans() }}
                                                    @endif
                                                    @if ($q->success_count !== null)
                                                        · {{ number_format($q->success_count) }} delivered
                                                    @endif
                                                </div>
                                            </div>
                                            <span
                                                class="text-[10.5px] font-mono text-ink-700">{{ number_format($q->total_recipients ?? 0) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1.5">
                                    {{ __('Tick any number of broadcasts. Recipients are de-duplicated automatically before sending.') }}
                                </div>
                            @endif
                        </div>

                        <!-- Numbers panel -->
                        <div class="rcp-panel hidden" data-panel="number">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Paste numbers') }}</label>
                            <textarea rows="4"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] resize-none focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('One number per line — include the country code, no plus sign') }}"></textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Country code first, no leading') }}
                                <span class="font-mono">+</span>. Newlines or commas separate entries.</div>
                        </div>

                        <div
                            class="mt-4 flex items-center gap-2.5 px-3 py-2 rounded-lg bg-wa-deep/5 border border-wa-deep/15">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep shrink-0" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <circle cx="6" cy="6" r="3" />
                                <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5" />
                                <circle cx="11.5" cy="5.5" r="2" />
                                <path d="M10.5 10c1.9.2 3.5 1.7 3.5 4" />
                            </svg>
                            <div class="text-[12px] text-ink-700 leading-snug">{{ __('Total recipients:') }} <b
                                    class="text-ink-900" id="rcp-total">0</b></div>
                        </div>
                    </div>

                    <!-- Step 3: Message -->
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Compose message') }}</span>
                            <span id="compose-type-pill"
                                class="font-mono text-[10px] text-ink-500">{{ __('plain text') }}</span>
                        </div>

                        <!-- Plain text composer -->
                        <div class="compose-pane" data-compose="plain">
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="msg-content">{{ __('Message content') }}</label>
                            <div class="border border-paper-200 rounded-lg overflow-hidden">
                                <div class="flex items-center gap-1 px-2 py-1.5 border-b border-paper-200 bg-paper-50">
                                    <button type="button"
                                        class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] font-bold">B</button>
                                    <button type="button"
                                        class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] italic">I</button>
                                    <button type="button"
                                        class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] line-through">S</button>
                                    <button type="button"
                                        class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] font-mono">m</button>
                                    <span class="w-px h-4 bg-paper-200 mx-1"></span>
                                    <button type="button"
                                        class="var-btn px-2 h-7 rounded hover:bg-white text-ink-700 text-[10.5px] font-mono"
                                        data-var="{name}">{name}</button>
                                    <button type="button"
                                        class="var-btn px-2 h-7 rounded hover:bg-white text-ink-700 text-[10.5px] font-mono"
                                        data-var="{phone}">{phone}</button>
                                    <button type="button"
                                        class="var-btn px-2 h-7 rounded hover:bg-white text-ink-700 text-[10.5px] font-mono"
                                        data-var="{email}">{email}</button>
                                    <span class="ml-auto text-[10px] font-mono text-ink-500"><span
                                            id="char-count">0</span> / 1024</span>
                                </div>
                                <textarea id="msg-content" rows="6" class="w-full px-3 py-2.5 text-[12.5px] focus:outline-none resize-none"
                                    placeholder="Hi {name}, your scheduled update is here…"
                                    oninput="document.getElementById('char-count').textContent=this.value.length;document.getElementById('pp-body').textContent=this.value||'your message will appear here…'"></textarea>
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Use') }} <span
                                    class="font-mono">{name}</span>, <span class="font-mono">{phone}</span>, <span
                                    class="font-mono">{email}</span> for personalization.</div>
                        </div>

                        <!-- Template composer -->
                        <div class="compose-pane hidden" data-compose="template">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Approved template') }}</label>
                            <div id="tpl-empty"
                                class="border-2 border-dashed border-paper-200 rounded-lg p-6 text-center hover:border-wa-deep transition cursor-pointer"
                                onclick="openTplModal()">
                                <span
                                    class="w-10 h-10 rounded-full bg-paper-50 inline-flex items-center justify-center mb-2"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <rect x="2.5" y="2.5" width="11" height="11" rx="1.5" />
                                        <path d="M2.5 6h11M6 13.5V6" />
                                    </svg></span>
                                <div class="text-[13px] font-semibold">{{ __('Choose an approved template') }}</div>
                                <div class="text-[10.5px] text-ink-500 font-mono mt-1">
                                    {{ __('Click to browse Marketing · Utility · Auth') }}</div>
                            </div>
                            <div id="tpl-selected" class="hidden border border-wa-deep rounded-lg p-4 bg-[#F0F8F6]">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="min-w-0">
                                        <div class="font-mono text-[10.5px] text-ink-500" id="tpl-sel-id">
                                            {{ __('spring_promo_v3') }}</div>
                                        <div class="font-serif text-[16px] leading-tight mt-0.5" id="tpl-sel-name">
                                            {{ __('Spring promo / VIP') }}</div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span
                                            class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-wa-mint text-wa-deep">{{ __('APPROVED') }}</span>
                                        <button type="button" onclick="openTplModal()"
                                            class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Change') }}</button>
                                    </div>
                                </div>
                                <div id="tpl-sel-body"
                                    class="text-[12.5px] leading-snug bg-paper-0 border border-paper-200 rounded-md p-2.5">
                                    Hi @{{ 1 }}, your Spring promo code @{{ 2 }} is active.
                                    Tap below to redeem.</div>
                                <div id="tpl-sel-meta" class="mt-2 text-[10.5px] font-mono text-ink-500">2 vars · 1
                                    CTA · footer</div>
                            </div>

                            {{-- This picker is a card grid, not a <select>, so the shared
 send-time mapping panel has nothing native to watch. A hidden
 mirror select carries the pick (kept in sync by pickTemplate in
 user-scheduled-create.js) — cheaper than a second panel that
 could drift from the campaign one. --}}
                            <select id="tlm-template-mirror" class="hidden" aria-hidden="true" tabindex="-1">
                                <option value="">—</option>
                                @foreach ($templates as $t)
                                    <option value="{{ $t->id }}">{{ $t->template_name }}</option>
                                @endforeach
                            </select>
                            <x-template-live-mapping :templates="$templates" target="#tlm-template-mirror" />
                        </div>

                        <!-- Media composer -->
                        <div class="compose-pane hidden" data-compose="media">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Media file') }}
                                <span class="text-accent-coral">*</span></label>
                            <div id="media-drop"
                                class="border-2 border-dashed border-paper-200 rounded-lg p-6 text-center hover:border-wa-deep transition cursor-pointer"
                                onclick="document.getElementById('media-file').click()">
                                <span
                                    class="w-10 h-10 rounded-full bg-paper-50 inline-flex items-center justify-center mb-2"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                        <circle cx="6" cy="7" r="1.2" />
                                        <path d="m3 11 3-3 4 4 3-3 0 4" />
                                    </svg></span>
                                <div class="text-[13px] font-semibold">{{ __('Drop file or') }} <span
                                        class="text-wa-deep">{{ __('browse') }}</span></div>
                                <div class="text-[10.5px] text-ink-500 font-mono mt-1">
                                    {{ __('Image · Video · Audio · PDF · DOC / max 5 MB') }}</div>
                                <input id="media-file" type="file" class="hidden"
                                    accept="image/*,video/*,audio/*,.pdf,.doc,.docx" onchange="handleMediaPick(this)">
                            </div>
                            <div id="media-preview"
                                class="hidden mt-3 flex items-center gap-3 border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <span
                                    class="w-12 h-12 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0"><svg
                                        viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="M4 2h6l3 3v9H4z" />
                                        <path d="M10 2v3h3" />
                                    </svg></span>
                                <div class="flex-1 min-w-0">
                                    <div id="media-name" class="text-[12.5px] font-semibold truncate">
                                        {{ __('file.pdf') }}</div>
                                    <div id="media-size" class="text-[10.5px] text-ink-500 font-mono">— KB</div>
                                </div>
                                <button type="button"
                                    onclick="document.getElementById('media-preview').classList.add('hidden');document.getElementById('media-file').value='';"
                                    class="text-[11px] text-accent-coral font-semibold hover:underline">{{ __('Remove') }}</button>
                            </div>

                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block mt-4"
                                for="media-cap">{{ __('Caption') }}</label>
                            <textarea id="media-cap" rows="3"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] resize-none focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Optional caption shown under the media…') }}"
                                oninput="document.getElementById('pp-body').textContent=this.value||'[ media attached ]'"></textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Captions support') }} <span
                                    class="font-mono">{name}</span>, <span class="font-mono">{phone}</span>, <span
                                    class="font-mono">{email}</span>.</div>
                        </div>

                        <!-- Location composer -->
                        <div class="compose-pane hidden" data-compose="location">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Pin location') }}
                                <span class="text-accent-coral">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[11px] text-ink-700 mb-1 block"
                                        for="loc-lat">{{ __('Latitude') }}</label>
                                    <input id="loc-lat" type="text" inputmode="decimal" placeholder="26.9124"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                        oninput="updateLocPreview()">
                                </div>
                                <div>
                                    <label class="text-[11px] text-ink-700 mb-1 block"
                                        for="loc-lng">{{ __('Longitude') }}</label>
                                    <input id="loc-lng" type="text" inputmode="decimal" placeholder="75.7873"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                        oninput="updateLocPreview()">
                                </div>
                            </div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block mt-3"
                                for="loc-name">{{ __('Place name') }} <span
                                    class="text-[10.5px] font-normal text-ink-500">(optional)</span></label>
                            <input id="loc-name" type="text" placeholder="{{ __('Brand HQ, Jaipur') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                oninput="updateLocPreview()">

                            <div class="mt-3 border border-paper-200 rounded-lg overflow-hidden">
                                <div
                                    class="bg-[linear-gradient(135deg,var(--color-wa-mint)_0%,var(--color-paper-0)_50%,#DFF1ED_100%)] h-[180px] relative grid place-items-center">
                                    <span
                                        class="absolute inset-0 [background-image:radial-gradient(rgba(7,94,84,0.08)_1px,transparent_1px)] bg-[length:18px_18px]"></span>
                                    <span class="relative">
                                        <svg viewBox="0 0 24 24" class="w-9 h-9 text-wa-deep drop-shadow"
                                            fill="currentColor">
                                            <path
                                                d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z" />
                                        </svg>
                                    </span>
                                </div>
                                <div
                                    class="px-3 py-2 bg-paper-0 text-[10.5px] font-mono text-ink-500 border-t border-paper-200">
                                    <span id="loc-readout">{{ __('Enter coordinates to drop a pin') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Live stats — JS keeps these in sync with the composer
 state. Throttle is 60 msg/min (per-plan override may come
 later); cost rate is shown in the workspace currency at
 the live exchange rate. All three cells show "—" until
 recipients are chosen. --}}
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Estimated send time') }}</div>
                                <div id="est-time" class="mt-1 font-serif text-[20px] leading-tight">—</div>
                                <div id="est-time-sub" class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('at 60 msg/min · pick recipients first.') }}</div>
                            </div>
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Variables in message') }}</div>
                                <div id="vars-filled" class="mt-1 font-serif text-[20px] leading-tight">0</div>
                                <div id="vars-filled-sub" class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('placeholders detected.') }}</div>
                            </div>
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Cost estimate') }}</div>
                                <div id="cost-estimate" class="mt-1 font-serif text-[20px] leading-tight">—</div>
                                <div id="cost-estimate-sub" class="text-[10.5px] text-ink-500 mt-1">
                                    {!! \App\Support\FormatSettings::display(0.007, 'USD') !!} per send.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: When -->
                    <div class="step-pane hidden" data-step="4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('When to send') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4" id="sch-modes">
                            <label
                                class="sch-mode cursor-pointer border rounded-xl p-4 transition border-wa-deep bg-[#F0F8F6]"
                                data-mode="once">
                                <div class="flex items-start justify-between mb-2">
                                    <span
                                        class="w-10 h-10 rounded-xl bg-wa-mint text-wa-deep grid place-items-center"><svg
                                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <circle cx="8" cy="8" r="6" />
                                            <path d="M8 5v3l2 2" />
                                        </svg></span>
                                    <span
                                        class="sch-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center opacity-100"><svg
                                            viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                            stroke-width="2.4">
                                            <path d="M3 8l3 3 7-8" />
                                        </svg></span>
                                </div>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Send once') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                    {{ __('A single delivery at the chosen date & time.') }}</p>
                            </label>
                            <label
                                class="sch-mode cursor-pointer border border-paper-200 bg-white rounded-xl p-4 transition hover:border-wa-deep"
                                data-mode="recurring">
                                <div class="flex items-start justify-between mb-2">
                                    <span
                                        class="w-10 h-10 rounded-xl bg-[#D9E5F2] text-[#13478A] grid place-items-center"><svg
                                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <path
                                                d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                        </svg></span>
                                    <span
                                        class="sch-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center opacity-0"><svg
                                            viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                            stroke-width="2.4">
                                            <path d="M3 8l3 3 7-8" />
                                        </svg></span>
                                </div>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Recurring') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                    {{ __('Repeat daily, weekly, or monthly on a fixed cadence.') }}</p>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Send date') }}</label>
                                {{-- Default to TODAY in the workspace's timezone. Previously
 defaulted to tomorrow which silently shifted every send by
 24h when the operator only adjusted the time. --}}
                                @php $userTz = optional(auth()->user()?->currentWorkspace)->timezone ?? 'Asia/Kolkata'; @endphp
                                <input id="sch-date" name="send_date" type="date"
                                    min="{{ now($userTz)->toDateString() }}"
                                    value="{{ now($userTz)->toDateString() }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    required>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Time') }}</label>
                                <input id="sch-time" name="send_time" type="time"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="10:00" required>
                            </div>
                            <div class="col-span-2">
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Timezone') }}</label>
                                <select id="sch-tz" name="timezone"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    required>
                                    @php
                                        $userTz =
                                            optional(auth()->user()?->currentWorkspace)->timezone ?? 'Asia/Kolkata';
                                        $tzList = \DateTimeZone::listIdentifiers();
                                    @endphp
                                    @foreach ($tzList as $tz)
                                        <option value="{{ $tz }}"
                                            {{ $tz === $userTz ? 'selected' : '' }}>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Recurring options -->
                        <div id="recurring-opts" class="hidden border border-paper-200 rounded-lg p-4 bg-paper-50/40">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                                {{ __('Recurrence') }}</div>
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label
                                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Repeat every') }}</label>
                                    <div class="flex gap-2">
                                        <input type="number" min="1" value="1"
                                            class="w-20 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                                        <select
                                            class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                                            <option>{{ __('day(s)') }}</option>
                                            <option selected>{{ __('week(s)') }}</option>
                                            <option>{{ __('month(s)') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('End') }}</label>
                                    <select
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                                        <option>{{ __('Never') }}</option>
                                        <option>{{ __('After N occurrences') }}</option>
                                        <option>{{ __('On a specific date') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Days of week') }}</label>
                                <div class="flex gap-1.5" id="dow-row">
                                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                                        <button type="button"
                                            class="dow w-9 h-9 rounded-full border border-paper-200 text-[11px] font-mono hover:border-wa-deep">{{ $day }}</button>
                                    @endforeach
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1.5">
                                    {{ __('Click any day to toggle. Weekly schedules need at least one.') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Review -->
                    <div class="step-pane hidden" data-step="5">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Review & schedule') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('final') }}</span>
                        </div>

                        {{-- Every cell here is filled by syncReview() in JS — no static
 values. Recipients pulls from the live audience total;
 est-time and cost use the same constants as Step 3. --}}
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                    {{ __('Schedule') }}</div>
                                <dl class="space-y-1.5 text-[12px]">
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Name') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-name">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Device') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-dev">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Type') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-type">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Mode') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-mode">—</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                    {{ __('Delivery') }}</div>
                                <dl class="space-y-1.5 text-[12px]">
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Recipient source') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-rcp">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Recipients') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-rcp-count">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Estimated time') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-est-time">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Cost estimate') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-cost">—</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        @php
                            // The preview goes to the operator's own WhatsApp number.
                            // Without one there is nowhere to send it, so the toggle is
                            // disabled with a pointer to where they add it — rather than
                            // letting them flip it and silently receive nothing.
                            $ownNumber = preg_replace('/\D+/', '', (string) (auth()->user()->mobile ?? ''));
                            $canTest   = strlen($ownNumber) >= 8;
                        @endphp
                        <div
                            class="flex items-center justify-between bg-paper-50/60 border border-paper-200 rounded-lg p-3">
                            <div>
                                <div class="text-[13px] font-semibold">{{ __('Send a test to me first') }}</div>
                                <div class="text-[11px] text-ink-500">
                                    @if ($canTest)
                                        {{ __('Receive one preview message at your own number before the full blast.') }}
                                    @else
                                        {{ __('Add your WhatsApp number in Account settings to preview a message before the full blast.') }}
                                    @endif
                                </div>
                            </div>
                            <label class="relative inline-flex items-center {{ $canTest ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' }}">
                                <input type="checkbox" id="sched-send-test" name="send_test" value="1"
                                       class="sr-only peer" @disabled(!$canTest)>
                                <span
                                    class="w-10 h-[22px] rounded-full bg-paper-200 peer-checked:bg-wa-deep transition-colors"></span>
                                <span
                                    class="absolute top-0.5 left-0.5 w-[18px] h-[18px] rounded-full bg-paper-0 shadow transition-transform peer-checked:translate-x-[18px]"></span>
                            </label>
                        </div>
                    </div>

                </div>

                <!-- Step nav -->
                <div class="px-5 py-4 flex items-center justify-between bg-paper-50/40 border-t border-paper-200">
                    <button type="button" id="btn-prev"
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium text-ink-700 inline-flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M10 4l-4 4 4 4" />
                        </svg>
                        Previous
                    </button>
                    <div class="font-mono text-[11px] text-ink-500">{{ __('Step') }} <span
                            id="cur-step">1</span> of 5</div>
                    <button type="button" id="btn-next"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                        Next
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M6 4l4 4-4 4" />
                        </svg>
                    </button>
                    <button type="submit" id="btn-submit"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold hidden items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M2 8l5 5 7-9" />
                        </svg>
                        Schedule message
                    </button>
                </div>

            </div>

            <!-- Right preview rail -->
            <aside class="space-y-4 xl:sticky xl:top-[78px] xl:self-start">
                <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live preview') }}</span>
                        <span class="font-mono text-[10px] text-wa-deep">{{ __('WhatsApp') }}</span>
                    </div>
                    <div class="p-4 bg-paper-50/40">
                        <div
                            class="mx-auto w-[260px] rounded-[24px] border-[6px] border-ink-900 overflow-hidden bg-paper-0 shadow-soft">
                            <div class="bg-wa-deep px-3 py-2 flex items-center gap-2">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-teal text-paper-0 grid place-items-center text-[11px] font-semibold">B</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12px] text-paper-0 font-semibold leading-tight truncate">
                                        {{ __('Brand Inc.') }}</div>
                                    <div class="text-[9.5px] text-paper-0/70 leading-tight">
                                        {{ __('scheduled · 18:30') }}</div>
                                </div>
                            </div>
                            <div
                                class="p-3 bg-wa-chat min-h-[260px] [background-image:radial-gradient(rgba(7,94,84,0.06)_1px,transparent_1px)] bg-[length:14px_14px]">
                                {{-- Single bubble, three slots stacked: image OR map OR none,
 then template-name eyebrow (only for template mode), then
 the body text. JS toggles the slots based on message type. --}}
                                <div
                                    class="bg-paper-0 rounded-[7px] rounded-tl-[2px] px-2.5 py-1.5 max-w-[88%] shadow-[0_1px_1px_rgba(0,0,0,0.06)] text-[12px] leading-snug">
                                    <img id="pp-img" alt=""
                                        class="hidden mb-1.5 w-full max-h-[180px] object-cover rounded-md bg-paper-100" />
                                    <iframe id="pp-map" class="hidden mb-1.5 w-full h-[140px] rounded-md border-0"
                                        loading="lazy" referrerpolicy="no-referrer"></iframe>
                                    <div id="pp-tpl-eyebrow"
                                        class="hidden font-mono text-[9.5px] text-wa-deep uppercase tracking-wider mb-0.5">
                                    </div>
                                    <div id="pp-body">{{ __('Your message will appear here…') }}</div>
                                    <div class="text-[9px] text-ink-500 text-right mt-1 font-mono">18:30</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

        </form>
    </section>

    <!-- Template picker modal -->
    <div id="tpl-modal" class="hidden fixed inset-0 z-50 items-center justify-center p-5"
        style="background:rgba(11,31,28,0.46);" onclick="if(event.target===this)closeTplModal()">
        <div
            class="bg-paper-0 rounded-2xl w-full max-w-[820px] max-h-[88vh] flex flex-col shadow-soft border border-paper-200">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="font-serif text-[16px] italic text-wa-deep leading-tight">{{ __('templates') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight tracking-[-0.01em]">{{ __('Choose a template') }}
                    </h3>
                    <p class="mt-0.5 text-[12px] text-ink-500">{{ __('Only approved, active templates are shown.') }}
                    </p>
                </div>
                <button type="button" onclick="closeTplModal()"
                    class="w-9 h-9 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>
            <div class="px-5 py-3 border-b border-paper-200 flex items-center gap-2 flex-wrap">
                <div class="relative flex-1 min-w-[200px]">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.6">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input id="tpl-search" type="search" placeholder="{{ __('Search by name, id, or content…') }}"
                        class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        oninput="renderTplList()">
                </div>
                <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1" id="tpl-cats">
                    <button type="button"
                        class="px-3 py-1 rounded-full text-[11.5px] font-semibold bg-wa-deep text-paper-0"
                        data-cat="all">{{ __('All') }}</button>
                    <button type="button"
                        class="px-3 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100"
                        data-cat="Marketing">{{ __('Marketing') }}</button>
                    <button type="button"
                        class="px-3 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100"
                        data-cat="Utility">{{ __('Utility') }}</button>
                    <button type="button"
                        class="px-3 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100"
                        data-cat="Authentication">{{ __('Auth') }}</button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-5">
                <div id="tpl-list" class="grid grid-cols-2 gap-3"></div>
            </div>
            <div class="px-5 py-3 border-t border-paper-200 flex items-center justify-between bg-paper-50/40">
                <a href="{{ url('/templates') }}"
                    class="text-[12px] text-wa-deep font-semibold hover:underline">{{ __('Manage templates →') }}</a>
                <button type="button" onclick="closeTplModal()"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>

</x-layouts.user>

@php
    /** @var array $chatState injected by ChatController@index */
    $chatState = $chatState ?? ['counts' => [], 'devices' => [], 'urls' => [], 'csrfToken' => csrf_token()];
    $counts = $chatState['counts'] ?? [];
    $tplCounts = $counts['templates'] ?? [];
    $devices = $chatState['devices'] ?? [];
    // Multi-engine: connected senders across ALL enabled engines, each with a
    // composite `engine:id` key. Grouped by engine (label "Unofficial API" for
    // baileys — never "Baileys") so a workspace running several engines at once
    // can pick which channel sends this queue. Single-engine workspaces get one
    // group with no header — identical to the old flat device list.
    $senders = $chatState['senders'] ?? [];
    $sendersByEngine = collect($senders)->groupBy('engine');
    $senderEngineCount = $sendersByEngine->count();
@endphp

<x-layouts.user :title="__('Quick Send')" nav-key="more" page="user-chat-index">

    <main class="chat-main max-w-none mx-auto px-4 sm:px-6 py-4 h-[calc(100vh-64px)] overflow-hidden">
        <div class="chat-frame h-full min-h-0 grid grid-cols-1 xl:grid-cols-[250px_400px_minmax(0,1fr)] gap-3">
            <aside
                class="chat-col-nav bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-3 overflow-hidden flex flex-col min-h-0">
                <div class="font-serif text-[17px] leading-tight text-wa-deep italic">{{ __('send queue') }}</div>
                <h1 class="font-serif text-[22px] leading-tight tracking-[-0.01em]">{{ __('Quick Send') }}</h1>

                <div class="mt-3 space-y-0.5" id="filter-list">
                    <button data-filter="all" aria-pressed="true"
                        class="filter-btn w-full px-3 py-1.5 rounded-xl text-[13px] font-medium flex items-center gap-2 text-ink-700 hover:bg-paper-50 [&[aria-pressed=true]]:bg-wa-deep [&[aria-pressed=true]]:text-paper-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M3 5h10l-1 8H4zM3 3h10v2H3z" />
                        </svg>
                        <span class="flex-1 text-left">{{ __('All chats') }}</span>
                        <span data-count="all"
                            class="rounded-full border border-current px-1.5 py-0.5 text-[10px] {{ $counts['all'] ?? 0 ? '' : 'hidden' }}">{{ $counts['all'] ?? 0 }}</span>
                    </button>
                    <button data-filter="scheduled" aria-pressed="false"
                        class="filter-btn w-full px-3 py-1.5 rounded-xl text-[13px] font-medium flex items-center gap-2 text-ink-700 hover:bg-paper-50 [&[aria-pressed=true]]:bg-wa-deep [&[aria-pressed=true]]:text-paper-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <rect x="2.5" y="3.5" width="11" height="10" rx="1.5" />
                            <path d="M2.5 6.5h11M5 2v3M11 2v3" />
                        </svg>
                        <span class="flex-1 text-left">{{ __('Scheduled') }}</span>
                        <span data-count="scheduled"
                            class="rounded-full border border-current px-1.5 py-0.5 text-[10px] {{ $counts['scheduled'] ?? 0 ? '' : 'hidden' }}">{{ $counts['scheduled'] ?? 0 }}</span>
                    </button>
                    <button data-filter="archived" aria-pressed="false"
                        class="filter-btn w-full px-3 py-1.5 rounded-xl text-[13px] font-medium flex items-center gap-2 text-ink-700 hover:bg-paper-50 [&[aria-pressed=true]]:bg-wa-deep [&[aria-pressed=true]]:text-paper-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M3 6h10v7H3zM2.5 3.5h11V6h-11zM6 9h4" />
                        </svg>
                        <span class="flex-1 text-left">{{ __('Archived') }}</span>
                        <span data-count="archived"
                            class="rounded-full border border-current px-1.5 py-0.5 text-[10px] {{ $counts['archived'] ?? 0 ? '' : 'hidden' }}">{{ $counts['archived'] ?? 0 }}</span>
                    </button>
                    <button data-filter="sent" aria-pressed="false"
                        class="filter-btn w-full px-3 py-1.5 rounded-xl text-[13px] font-medium flex items-center gap-2 text-ink-700 hover:bg-paper-50 [&[aria-pressed=true]]:bg-wa-deep [&[aria-pressed=true]]:text-paper-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M2.5 8.5 5.5 12 8 8.5M7 9l3 3.5 4-8" />
                        </svg>
                        <span class="flex-1 text-left">{{ __('Sent') }}</span>
                        <span data-count="sent"
                            class="rounded-full border border-current px-1.5 py-0.5 text-[10px] {{ $counts['sent'] ?? 0 ? '' : 'hidden' }}">{{ $counts['sent'] ?? 0 }}</span>
                    </button>
                    <button data-filter="pending" aria-pressed="false"
                        class="filter-btn w-full px-3 py-1.5 rounded-xl text-[13px] font-medium flex items-center gap-2 text-ink-700 hover:bg-paper-50 [&[aria-pressed=true]]:bg-wa-deep [&[aria-pressed=true]]:text-paper-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <circle cx="8" cy="8" r="5.5" />
                            <path d="M8 5v3l2 1.5" />
                        </svg>
                        <span class="flex-1 text-left">{{ __('Pending') }}</span>
                        <span data-count="pending"
                            class="rounded-full border border-current px-1.5 py-0.5 text-[10px] {{ $counts['pending'] ?? 0 ? '' : 'hidden' }}">{{ $counts['pending'] ?? 0 }}</span>
                    </button>
                    <button data-filter="failed" aria-pressed="false"
                        class="filter-btn w-full px-3 py-1.5 rounded-xl text-[13px] font-medium flex items-center gap-2 text-ink-700 hover:bg-paper-50 [&[aria-pressed=true]]:bg-wa-deep [&[aria-pressed=true]]:text-paper-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <circle cx="8" cy="8" r="5.5" />
                            <path d="M5.5 5.5 10.5 10.5M10.5 5.5 5.5 10.5" />
                        </svg>
                        <span class="flex-1 text-left">{{ __('Failed') }}</span>
                        <span data-count="failed"
                            class="rounded-full border border-current px-1.5 py-0.5 text-[10px] {{ $counts['failed'] ?? 0 ? '' : 'hidden' }}">{{ $counts['failed'] ?? 0 }}</span>
                    </button>
                </div>

                <div class="my-3 border-t border-dashed border-paper-200"></div>

                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5">
                    {{ __('Templates') }}</div>
                <div class="space-y-1.5">
                    <button data-template-category="marketing"
                        class="w-full px-3 py-1.5 rounded-xl bg-white border border-paper-200 flex items-center gap-2 text-[13px] hover:border-wa-deep">
                        <span class="w-2 h-2 rounded-full bg-accent-coral"></span>
                        <span class="flex-1 text-left font-medium">{{ __('Marketing') }}</span>
                        <span data-tpl-count="marketing"
                            class="font-mono text-[11px] text-ink-500">{{ $tplCounts['marketing'] ?? 0 }}</span>
                    </button>
                    <button data-template-category="utility"
                        class="w-full px-3 py-1.5 rounded-xl bg-white border border-paper-200 flex items-center gap-2 text-[13px] hover:border-wa-deep">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                        <span class="flex-1 text-left font-medium">{{ __('Utility') }}</span>
                        <span data-tpl-count="utility"
                            class="font-mono text-[11px] text-ink-500">{{ $tplCounts['utility'] ?? 0 }}</span>
                    </button>
                    <button data-template-category="authentication"
                        class="w-full px-3 py-1.5 rounded-xl bg-white border border-paper-200 flex items-center gap-2 text-[13px] hover:border-wa-deep">
                        <span class="w-2 h-2 rounded-full bg-[#7B61FF]"></span>
                        <span class="flex-1 text-left font-medium">{{ __('Authentication') }}</span>
                        <span data-tpl-count="authentication"
                            class="font-mono text-[11px] text-ink-500">{{ $tplCounts['authentication'] ?? 0 }}</span>
                    </button>
                </div>

                <div class="my-3 border-t border-dashed border-paper-200"></div>

                <div class="rounded-[14px] border border-wa-green/40 bg-wa-mint/40 p-2.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                        <span class="font-semibold text-[13px]">{{ __('Live inbox') }}</span>
                    </div>
                    <p class="mt-1 text-[11px] text-ink-600 leading-snug">{{ count($devices) }} devices connected.
                        Replies update on the selected queue.</p>
                </div>
            </aside>

            <section
                class="chat-col-queues bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-3 flex flex-col min-h-0 overflow-hidden">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="font-serif text-[17px] leading-tight text-wa-deep italic">{{ __('bulk send') }}
                        </div>
                        <h2 class="font-serif text-[22px] leading-tight tracking-[-0.01em]">{{ __('Message queues') }}
                        </h2>
                    </div>
                    <button id="compose-btn" type="button"
                        class="w-10 h-10 rounded-full border border-paper-200 bg-white hover:bg-wa-mint flex items-center justify-center"
                        title="{{ __('Compose queue') }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M3 13h3L13 6 10 3 3 10zM9.5 3.5l3 3M10 13h3" />
                        </svg>
                    </button>
                </div>

                <div class="mt-3 relative">
                    <svg viewBox="0 0 16 16" class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input id="queue-search"
                        class="w-full pl-9 pr-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="{{ __('Search queues...') }}">
                </div>

                <div class="mt-2 grid grid-cols-[minmax(0,1fr)_112px] items-center gap-2">
                    <select id="device-select"
                        class="w-full min-w-0 px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] font-medium focus:outline-none focus:border-wa-deep">
                        <option value="">{{ __('All devices') }}</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device['id'] ?? '' }}">
                                {{ $device['label'] }}{{ $device['online'] ?? true ? '' : ' — offline' }}</option>
                        @endforeach
                    </select>
                    <select id="sort-select"
                        class="w-full min-w-0 px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] font-medium focus:outline-none focus:border-wa-deep">
                        <option value="date-desc">{{ __('Newest') }}</option>
                        <option value="date-asc">{{ __('Oldest') }}</option>
                        <option value="name-asc">{{ __('A to Z') }}</option>
                        <option value="name-desc">{{ __('Z to A') }}</option>
                    </select>
                </div>

                <div class="mt-2 rounded-xl bg-paper-50 px-3 py-1.5 text-[11px] text-ink-600 leading-snug">
                    {{ __('Select a queue to open the thread and send a test reply.') }}</div>

                <div id="queue-list" class="mt-2 flex-1 min-h-0 overflow-y-auto space-y-1.5 pr-1">
                    {{-- Shimmer skeletons during the first conversations.list()
 fetch. The JS replaces this whole node on success. --}}
                    @for ($i = 0; $i < 4; $i++)
                        <div class="rounded-[14px] border border-paper-200 bg-white p-2.5">
                            <div class="flex items-start gap-3">
                                <div class="skeleton w-9 h-9 rounded-full shrink-0"></div>
                                <div class="flex-1 min-w-0 space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <div class="skeleton h-3 w-2/5"></div>
                                        <div class="skeleton ml-auto h-2.5 w-8"></div>
                                    </div>
                                    <div class="skeleton h-2.5 w-4/5"></div>
                                    <div class="flex items-center gap-2">
                                        <div class="skeleton h-2 w-12"></div>
                                        <div class="skeleton ml-auto h-2 w-14"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>

                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button id="send-all"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-full bg-ink-900 text-paper-0 text-[12px] font-semibold hover:bg-wa-deep">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M2 8 14 3 10 14 7 9z" />
                        </svg>
                        Send to all
                    </button>
                    <button id="send-selected"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M3 8l3 3 7-7" />
                            <circle cx="3" cy="3" r="1.5" />
                        </svg>
                        Send to selected
                    </button>
                </div>
            </section>

            <section
                class="chat-col-thread bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden flex flex-col min-h-0">
                <div class="px-4 py-3 border-b border-paper-200 bg-paper-0 flex items-center gap-3">
                    <button id="chat-thread-back" type="button"
                        class="chat-mobile-back w-9 h-9 -ml-1 rounded-full hover:bg-paper-50 grid place-items-center shrink-0"
                        title="{{ __('Back to queues') }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M10 3l-4 5 4 5" />
                        </svg>
                    </button>
                    <div id="thread-avatar"
                        class="w-10 h-10 rounded-full border border-ink-900/15 bg-[#FFF6E0] grid place-items-center font-bold text-[13px]">
                        --</div>
                    <div class="flex-1 min-w-0">
                        <div id="thread-title" class="font-semibold truncate">{{ __('Select a queue') }}</div>
                        <div class="flex items-center gap-1.5 text-[12px] text-ink-500">
                            <span id="thread-dot" class="w-2 h-2 rounded-full bg-ink-500"></span>
                            <span id="thread-meta">{{ __('No active thread') }}</span>
                        </div>
                    </div>
                    <button id="thread-ai" type="button"
                        class="w-8 h-8 rounded-full border border-wa-deep/40 bg-gradient-to-br from-wa-mint to-paper-0 hover:from-wa-mint hover:to-wa-mint flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed"
                        title="{{ __('AI assist') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <path
                                d="M8 2.5L9.4 6 13 7l-3.6 1L8 11.5 6.6 8 3 7l3.6-1zM12 11.5l.6 1.4L14 13.5l-1.4.6L12 15.5l-.6-1.4L10 13.5l1.4-.6z" />
                        </svg>
                    </button>
                    <button id="thread-archive" type="button"
                        class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed"
                        title="{{ __('Archive') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M3 6h10v7H3zM2.5 3.5h11V6h-11zM6 9h4" />
                        </svg>
                    </button>
                    <button id="thread-more" type="button"
                        class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed"
                        title="{{ __('More') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                            <circle cx="8" cy="3.5" r="1" />
                            <circle cx="8" cy="8" r="1" />
                            <circle cx="8" cy="12.5" r="1" />
                        </svg>
                    </button>
                </div>

                <div id="message-list"
                    class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3 bg-[radial-gradient(circle_at_18%_22%,rgba(21,40,31,0.06)_0_1px,transparent_1px),radial-gradient(circle_at_72%_64%,rgba(21,40,31,0.06)_0_1px,transparent_1px)] bg-[length:28px_28px]">
                </div>

                <div class="px-3 py-2 border-t border-paper-200 bg-paper-0">
                    {{-- "Scheduled for…" indicator. JS toggles .hidden when the
 user picks a date/time from the schedule modal. --}}
                    <div id="schedule-indicator"
                        class="hidden mb-2 rounded-2xl border border-[#13478A]/30 bg-[#13478A]/5 px-3 py-2 flex items-center gap-2 text-[12px] font-semibold text-[#13478A]">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <circle cx="8" cy="8" r="5.5" />
                            <path d="M8 5v3l2 1.5" />
                        </svg>
                        <span id="schedule-indicator-text" class="flex-1"></span>
                        <button id="schedule-indicator-clear" type="button"
                            class="w-5 h-5 rounded-full border border-[#13478A]/30 hover:bg-white grid place-items-center"
                            title="{{ __('Cancel schedule') }}">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                        </button>
                    </div>

                    {{-- Media preview banner (shown after picking a file from the
 attach menu, before the message is sent). The JS toggles
 .hidden and fills #media-preview-body with a thumbnail
 + filename + size; the X button clears the pending file. --}}
                    <div id="media-preview"
                        class="hidden mb-2 rounded-2xl border border-wa-deep/20 bg-wa-mint/40 px-3 py-2 flex items-center gap-3">
                        <div id="media-preview-thumb"
                            class="w-12 h-12 rounded-xl bg-paper-0 border border-paper-200 grid place-items-center overflow-hidden shrink-0">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div id="media-preview-name" class="font-semibold text-[13px] truncate"></div>
                            <div id="media-preview-meta" class="text-[11px] text-ink-500 font-mono"></div>
                        </div>
                        <button id="media-preview-remove" type="button"
                            class="w-7 h-7 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center"
                            title="{{ __('Remove attachment') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                        </button>
                    </div>

                    <div
                        class="relative flex items-end gap-1.5 rounded-[24px] border border-paper-200 bg-white pr-1.5 pl-1.5 py-1.5 shadow-card focus-within:border-wa-deep focus-within:ring-4 focus-within:ring-wa-deep/10">
                        <button id="attach-btn"
                            class="shrink-0 w-9 h-9 rounded-full hover:bg-wa-mint grid place-items-center text-ink-700"
                            title="{{ __('Attach') }}" aria-expanded="false" aria-controls="attach-menu">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                        </button>
                        <div id="attach-menu"
                            class="hidden absolute left-1 bottom-12 z-30 w-56 rounded-2xl border border-paper-200 bg-paper-0 shadow-soft p-1.5">
                            <button data-attach="Template"
                                class="w-full px-3 py-2 rounded-xl text-left text-[13px] font-medium hover:bg-wa-mint flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <rect x="2.5" y="2.5" width="11" height="11" rx="1.5" />
                                    <path d="M2.5 6h11M6 13.5V6" />
                                </svg>
                                Template
                            </button>
                            <button data-attach="Document"
                                class="w-full px-3 py-2 rounded-xl text-left text-[13px] font-medium hover:bg-wa-mint flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <path d="M4 2h6l3 3v9H4zM10 2v3h3" />
                                </svg>
                                Document
                            </button>
                            <button data-attach="Photos & videos"
                                class="w-full px-3 py-2 rounded-xl text-left text-[13px] font-medium hover:bg-wa-mint flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <rect x="2.5" y="3" width="11" height="10" rx="1.5" />
                                    <circle cx="6" cy="6.5" r="1.2" />
                                    <path d="M3 11l3-3 3 3 2-2 2 2" />
                                </svg>
                                Photos & videos
                            </button>
                            <button data-attach="Audio"
                                class="w-full px-3 py-2 rounded-xl text-left text-[13px] font-medium hover:bg-wa-mint flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <path d="M8 2v12M5 6a3 3 0 0 1 6 0M3 8a5 5 0 0 0 10 0" />
                                </svg>
                                Audio
                            </button>
                            <button data-attach="Scheduled time"
                                class="w-full px-3 py-2 rounded-xl text-left text-[13px] font-medium hover:bg-wa-mint flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <circle cx="8" cy="8" r="5.5" />
                                    <path d="M8 5v3l2 1.5" />
                                </svg>
                                Scheduled time
                            </button>
                            <button data-attach="Send your location"
                                class="w-full px-3 py-2 rounded-xl text-left text-[13px] font-medium hover:bg-wa-mint flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <path d="M8 2.5a5 5 0 0 0-5 5c0 3.8 5 7 5 7s5-3.2 5-7a5 5 0 0 0-5-5Z" />
                                    <circle cx="8" cy="7.5" r="1.6" />
                                </svg>
                                Send your location
                            </button>
                        </div>
                        <input type="file" id="media-input"
                            accept="image/*,video/*,audio/*,application/pdf,.doc,.docx" class="hidden">
                        <button id="emoji-btn"
                            class="shrink-0 w-9 h-9 rounded-full hover:bg-wa-mint grid place-items-center text-ink-700"
                            title="{{ __('Emoji') }}" aria-expanded="false" aria-controls="emoji-panel">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <circle cx="8" cy="8" r="5.5" />
                                <path d="M5.8 9.5s.8 1.3 2.2 1.3 2.2-1.3 2.2-1.3M6.2 6.4h.01M9.8 6.4h.01" />
                            </svg>
                        </button>
                        {{-- Real emoji picker — emoji-picker-element web component
 is mounted here by the JS on first open. Markup is just
 a host wrapper; categories / search / skin tones come
 from the package. --}}
                        <div id="emoji-panel"
                            class="hidden absolute left-11 bottom-12 z-30 rounded-2xl border border-paper-200 bg-paper-0 shadow-soft overflow-hidden">
                            <div id="emoji-mount"></div>
                        </div>
                        <button id="format-bold"
                            class="shrink-0 w-9 h-9 rounded-full hover:bg-wa-mint grid place-items-center text-ink-700 font-bold text-[13px]"
                            title="{{ __('Bold') }}">B</button>
                        <textarea id="composer" rows="1"
                            class="flex-1 min-h-9 max-h-[92px] px-3 py-2 bg-transparent text-[13px] resize-none focus:outline-none leading-relaxed"
                            placeholder="{{ __('Type a message') }}"></textarea>
                        <button id="send-btn"
                            class="shrink-0 w-10 h-10 rounded-full bg-ink-900 text-paper-0 hover:bg-wa-deep disabled:opacity-40 disabled:cursor-not-allowed grid place-items-center"
                            title="{{ __('Send') }}" disabled>
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M2 8 14 3 10 14 7 9z" />
                            </svg>
                        </button>
                    </div>
                    <div id="quick-bar" class="hidden">
                        <button
                            data-template="Hi @{{ name }}, thanks for your reply. Our team will help you shortly."
                            class="px-2.5 py-1 rounded-full bg-paper-50 hover:bg-wa-mint">{{ __('Support reply') }}</button>
                        <button
                            data-template="Your order update is ready. Please confirm your preferred delivery slot."
                            class="px-2.5 py-1 rounded-full bg-paper-50 hover:bg-wa-mint">{{ __('Order update') }}</button>
                        <button data-template="Use code NEW26 before Jan 10 to claim the offer."
                            class="px-2.5 py-1 rounded-full bg-paper-50 hover:bg-wa-mint">{{ __('Offer code') }}</button>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <div id="template-modal"
        class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
        <div
            {{-- 6xl, not 5xl: the right rail now carries the send-time mapping
                 panel as well as the preview, and the card grid must not lose
                 its two columns to pay for it. --}}
            class="w-full max-w-6xl max-h-[86vh] overflow-hidden flex flex-col bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)]">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-4">
                <div>
                    <div id="template-modal-eyebrow" class="font-serif text-[18px] italic text-wa-deep leading-tight">
                        {{ __('templates') }}</div>
                    <h3 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">{{ __('Choose a template') }}
                    </h3>
                    <p class="mt-1 text-[12px] text-ink-500">
                        {{ __('Pick a template card and send it to the current queue thread.') }}</p>
                </div>
                <button id="template-modal-close"
                    class="w-9 h-9 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] min-h-0 flex-1">
                <div class="p-4 min-h-0 overflow-y-auto">
                    <div id="template-card-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
                </div>
                <aside class="border-l border-paper-200 bg-paper-50 p-4 flex flex-col min-h-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 shrink-0">
                        {{ __('Preview') }}
                    </div>
                    {{-- ONE scroll region for the preview + the mapping panel.
                         They were separate boxes before, which let the panel
                         push the preview to nothing and left neither
                         scrollable. The queue + send button below stay pinned. --}}
                    <div class="mt-3 flex-1 min-h-0 overflow-y-auto space-y-3 pr-1">
                        <div id="template-preview"
                            class="rounded-[14px] border border-paper-200 bg-white p-4 text-[13px] leading-relaxed text-ink-600">
                            {{ __('Select a template to preview it here.') }}
                        </div>
                        {{-- Send-time mapping — the same panel campaigns, broadcasts,
                             scheduled and the team inbox use, mounted directly. Fill
                             variable values / header media / button links for THIS
                             send without editing the template. --}}
                        <div id="chat-tlm-panel" class="hidden"></div>
                    </div>
                    <div class="mt-4 shrink-0">
                        <div class="text-[11px] text-ink-500 mb-2">{{ __('Target queue') }}</div>
                        <div id="template-target"
                            class="rounded-xl border border-paper-200 bg-white px-3 py-2 text-[12px] font-semibold truncate">
                            {{ __('No queue selected') }}</div>
                    </div>
                    <button id="template-send"
                        class="mt-3 w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-full bg-ink-900 text-paper-0 text-[12px] font-semibold hover:bg-wa-deep disabled:opacity-40 disabled:cursor-not-allowed"
                        disabled>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M2 8 14 3 10 14 7 9z" />
                        </svg>
                        Send template
                    </button>
                </aside>
            </div>
        </div>
    </div>

    <div id="compose-modal"
        class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
        <div
            class="w-full max-w-5xl max-h-[100vh] overflow-hidden flex flex-col bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)]">

            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-4">
                <div>
                    <div class="font-serif text-[18px] italic text-wa-deep leading-tight">{{ __('compose') }}</div>
                    <h3 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">{{ __('Create a new queue') }}
                    </h3>
                    <p class="mt-1 text-[12px] text-ink-500">
                        {{ __('A queue is a single send to many recipients. Pick a device, choose who it goes to, and write the first message.') }}
                    </p>
                </div>
                <button id="compose-modal-close" type="button"
                    class="w-9 h-9 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            <form id="compose-form" class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_280px] min-h-0 flex-1" novalidate>

                {{-- Main form column --}}
                <div class="p-5 min-h-0 overflow-y-auto grid gap-4">

                    {{-- Queue name + device row --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Queue name') }}</span>
                            <input name="title" required maxlength="191"
                                class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('e.g. Diwali launch — VIPs') }}">
                        </label>
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 flex items-center justify-between">
                                <span>{{ __('Device') }}</span>
                                @if (!empty($chatState['canMultiDevice']))
                                    <span
                                        class="font-mono text-[10px] tracking-normal normal-case text-wa-deep">{{ __('Multi-device · plan unlocks split sending') }}</span>
                                @endif
                            </span>
                            @if (!empty($chatState['canMultiDevice']))
                                {{-- Multi-device plan: a real <select multiple> mounted
 with Tom Select (see user-chat-index.js). Tom Select
 turns the field into a tag/pill input — click an
 option to add, click the pill to remove. No Ctrl
 needed. The split bar below renders live as the
 operator changes devices or recipients. --}}
                                <select name="sender_keys[]" id="compose-devices" multiple
                                    placeholder="{{ __('Choose one or more senders…') }}" class="mt-1 w-full">
                                    @if ($senderEngineCount > 1)
                                        @foreach ($sendersByEngine as $engineKey => $engineSenders)
                                            <optgroup label="{{ $engineSenders->first()['engineLabel'] ?? __('Sender') }}">
                                                @foreach ($engineSenders as $sender)
                                                    <option value="{{ $sender['key'] }}"
                                                        data-label="{{ $sender['label'] }}"
                                                        data-online="{{ $sender['online'] ?? true ? '1' : '0' }}">
                                                        {{ $sender['label'] }}{{ $sender['online'] ?? true ? '' : ' — offline' }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    @else
                                        @foreach ($senders as $sender)
                                            <option value="{{ $sender['key'] }}"
                                                data-label="{{ $sender['label'] }}"
                                                data-online="{{ $sender['online'] ?? true ? '1' : '0' }}">
                                                {{ $sender['label'] }}{{ $sender['online'] ?? true ? '' : ' — offline' }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                <div id="compose-split-bar" class="mt-2 hidden">
                                    <div class="flex items-center justify-between mb-1">
                                        <span
                                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Split preview') }}</span>
                                        <span id="compose-split-summary"
                                            class="font-mono text-[10.5px] text-ink-500"></span>
                                    </div>
                                    <div id="compose-split-segments"
                                        class="flex h-7 rounded-lg overflow-hidden border border-paper-200 bg-paper-50">
                                    </div>
                                </div>
                                <span
                                    class="mt-1 block text-[10.5px] text-ink-500 leading-snug">{{ __('Click a device to add — recipients split evenly across the chosen devices. Click the chip to remove.') }}</span>
                            @else
                                <select name="sender" id="compose-sender"
                                    class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] font-medium focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="">{{ __('No sender selected') }}</option>
                                    @if ($senderEngineCount > 1)
                                        @foreach ($sendersByEngine as $engineKey => $engineSenders)
                                            <optgroup label="{{ $engineSenders->first()['engineLabel'] ?? __('Sender') }}">
                                                @foreach ($engineSenders as $sender)
                                                    <option value="{{ $sender['key'] }}">
                                                        {{ $sender['label'] }}{{ $sender['online'] ?? true ? '' : ' — offline' }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    @else
                                        @foreach ($senders as $sender)
                                            <option value="{{ $sender['key'] }}">
                                                {{ $sender['label'] }}{{ $sender['online'] ?? true ? '' : ' — offline' }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            @endif
                            @if (empty($senders))
                                <span
                                    class="mt-1 block text-[10.5px] text-accent-coral leading-snug">{{ __('No connected device.') }}
                                    <button type="button" data-connect-device
                                        class="font-semibold text-wa-deep hover:underline cursor-pointer">{{ __('Connect one →') }}</button></span>
                            @endif
                        </label>
                    </div>

                    <div class="border-t border-dashed border-paper-200"></div>

                    {{-- Recipients --}}
                    <fieldset class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <legend class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Recipients') }}</legend>
                            <span id="compose-rcpt-count" class="text-[11px] font-mono text-ink-500"></span>
                        </div>

                        <div
                            class="inline-flex rounded-full border border-paper-200 bg-paper-50 p-0.5 self-start text-[12px] font-semibold">
                            <label
                                class="px-3 py-1.5 rounded-full cursor-pointer transition has-[:checked]:bg-wa-deep has-[:checked]:text-paper-0 hover:bg-white">
                                <input type="radio" name="recipient_type" value="manual" class="sr-only" checked>
                                <span class="inline-flex items-center gap-1.5">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.5">
                                        <path d="M3 12l5-9 5 9z" />
                                        <path d="M5 12l3-5 3 5" />
                                    </svg>
                                    Paste numbers
                                </span>
                            </label>
                            <label
                                class="px-3 py-1.5 rounded-full cursor-pointer transition has-[:checked]:bg-wa-deep has-[:checked]:text-paper-0 hover:bg-white">
                                <input type="radio" name="recipient_type" value="group" class="sr-only">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.5">
                                        <circle cx="6" cy="6" r="2.5" />
                                        <circle cx="11" cy="7" r="2" />
                                        <path d="M2 13c.5-2 2.2-3 4-3s3.5 1 4 3M9.5 13c.5-1.5 1.7-2 3-2s2.4.5 2.5 2" />
                                    </svg>
                                    Pick a group
                                </span>
                            </label>
                        </div>

                        <textarea name="recipients" rows="3" data-mode="manual"
                            class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="+91 98xxxxxxxx, +44 78xxxxxxxx&#10;or one per line"></textarea>

                        <select name="contact_group_id" data-mode="group"
                            class="hidden w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] font-medium focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">{{ __('Select a group…') }}</option>
                            @foreach ($chatState['groups'] ?? [] as $group)
                                <option value="{{ $group['id'] }}" data-count="{{ $group['count'] }}">
                                    {{ $group['label'] }} ({{ $group['count'] }} contacts)</option>
                            @endforeach
                        </select>
                    </fieldset>

                    <div class="border-t border-dashed border-paper-200"></div>

                    {{-- Initial message — uses the shared <x-wa-editor> component
 so other blades can drop in the same toolbar (bold /
 italic / strike / code / emoji + Cmd-B / Cmd-I / Cmd-U
 keyboard shortcuts). --}}
                    <div class="block">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1 block">{{ __('Initial message') }}</span>
                        {{-- Placeholder built with chr() so the literal "{{" never
 appears in the template source — Blade scans attribute
 values for {{ }} echo statements regardless of context,
 and a plain placeholder="Hi {{name}}, …" gets rewritten
 into <?= e(name) ?>. The :placeholder=" …"value is a
 pure PHP expression which Blade does NOT pre-scan, so
 concatenating chr(123)/chr(125) yields {{name}} at
 runtime safely. --}}
                        <x-wa-editor name="body" :rows="4" required :placeholder="'Hi ' .
                            chr(123) .
                            chr(123) .
                            'name' .
                            chr(125) .
                            chr(125) .
                            ', your VIP early-access window opens tonight at 8pm. Use code `EARLY26`.'" />
                    </div>

                    {{-- Schedule toggle --}}
                    <div class="rounded-xl border border-paper-200 bg-paper-50/60 p-3 grid gap-2">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" id="compose-schedule-on"
                                class="mt-0.5 w-4 h-4 rounded border-paper-300 accent-wa-deep">
                            <span class="flex-1">
                                <span class="block text-[13px] font-semibold">{{ __('Send later') }}</span>
                                <span
                                    class="block text-[11px] text-ink-500 leading-snug">{{ __('Pick a time at least 1 minute in the future. Otherwise the queue sends immediately.') }}</span>
                            </span>
                        </label>
                        <div id="compose-schedule-fields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label class="block">
                                <span
                                    class="text-[11px] font-mono uppercase tracking-wide text-ink-500">{{ __('When') }}</span>
                                <input type="datetime-local" name="scheduled_at" id="compose-scheduled-at"
                                    class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="block">
                                <span
                                    class="text-[11px] font-mono uppercase tracking-wide text-ink-500">{{ __('Timezone') }}</span>
                                <select name="timezone" id="compose-timezone"
                                    class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                                    @php
                                        $userTz =
                                            optional(auth()->user()?->currentWorkspace)->timezone ??
                                            (auth()->user()?->timezone ?? config('app.timezone', 'UTC'));
                                    @endphp
                                    @foreach (\DateTimeZone::listIdentifiers() as $tz)
                                        <option value="{{ $tz }}" {{ $tz === $userTz ? 'selected' : '' }}>
                                            {{ $tz }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    <div id="compose-error"
                        class="hidden rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                    </div>
                </div>

                {{-- Sidebar (mirrors template-modal aesthetic) --}}
                <aside class="border-l border-paper-200 bg-paper-50 p-4 flex flex-col min-h-0 gap-3">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Live preview') }}</div>
                    <div id="compose-preview"
                        class="rounded-[14px] border border-paper-200 bg-white p-3 text-[12.5px] leading-relaxed text-ink-700 min-h-[140px]">
                        <span class="text-ink-500">{{ __('Type a message — preview lands here.') }}</span>
                    </div>

                    <div
                        class="rounded-[14px] border border-wa-green/40 bg-wa-mint/40 p-3 text-[11.5px] leading-relaxed">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                            <span class="font-semibold text-[12.5px]">{{ __('Tip') }}</span>
                        </div>
                        <p class="mt-1 text-ink-600">
                            Use <code
                                class="px-1 rounded bg-ink-900/10 font-mono text-[11px]">@{{ name }}</code>
                            to personalise per contact when sending to a group.
                        </p>
                    </div>

                    <div class="mt-auto grid gap-2">
                        <button type="submit" id="compose-submit"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-full bg-ink-900 text-paper-0 text-[12px] font-semibold hover:bg-wa-deep disabled:opacity-40">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M2 8 14 3 10 14 7 9z" />
                            </svg>
                            Create &amp; send
                        </button>
                        <button type="button" id="compose-cancel"
                            class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">{{ __('Cancel') }}</button>
                    </div>
                </aside>
            </form>
        </div>
    </div>

    <div id="queue-menu"
        class="hidden absolute z-40 w-44 rounded-2xl border border-paper-200 bg-paper-0 shadow-soft p-1.5"></div>

    {{--
 Right-side drawer for queue details. Sliding in from the right
 with a backdrop click area on the left half. Tabs swap between
 Overview / Recipients / Messages / Attachments.
--}}
    <div id="details-drawer" class="hidden fixed inset-0 z-50">
        <div id="details-backdrop" class="absolute inset-0 bg-[rgba(11,31,28,0.46)] transition-opacity"></div>
        <aside id="details-panel"
            class="absolute right-0 top-0 h-full w-full max-w-[640px] bg-paper-0 border-l border-paper-200 shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] flex flex-col translate-x-full transition-transform duration-200">

            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3 shrink-0">
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Queue details') }}</div>
                    <h3 id="details-title" class="font-serif text-[22px] leading-tight tracking-[-0.01em] truncate">—
                    </h3>
                    <div id="details-subtitle" class="mt-0.5 text-[12px] text-ink-500"></div>
                </div>
                <button id="details-close" type="button"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center shrink-0"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            {{-- Stats strip --}}
            <div id="details-stats"
                class="px-5 py-3 border-b border-paper-200 grid grid-cols-3 sm:grid-cols-6 gap-2 shrink-0"></div>

            {{-- Tabs — underline-style. JS toggles `details-tab-active` to
 drive the active visual; same pattern as the chat status
 filter rail. --}}
            <div class="px-5 border-b border-paper-200 shrink-0">
                <div id="details-tabs" class="flex gap-1 -mb-px">
                    <button data-details-tab="overview"
                        class="details-tab inline-flex items-center gap-1.5 px-3 py-2.5 text-[12.5px] font-semibold text-ink-500 hover:text-wa-deep border-b-2 border-transparent transition">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <circle cx="8" cy="8" r="5.5" />
                            <path d="M8 5v3l2 1.5" />
                        </svg>
                        Overview
                    </button>
                    <button data-details-tab="recipients"
                        class="details-tab inline-flex items-center gap-1.5 px-3 py-2.5 text-[12.5px] font-semibold text-ink-500 hover:text-wa-deep border-b-2 border-transparent transition">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <circle cx="6" cy="6" r="2.5" />
                            <circle cx="11" cy="7" r="2" />
                            <path d="M2 13c.5-2 2.2-3 4-3s3.5 1 4 3M9.5 13c.5-1.5 1.7-2 3-2s2.4.5 2.5 2" />
                        </svg>
                        Recipients
                        <span data-details-tab-count="recipients"
                            class="ml-0.5 font-mono text-[10px] text-ink-500"></span>
                    </button>
                    <button data-details-tab="messages"
                        class="details-tab inline-flex items-center gap-1.5 px-3 py-2.5 text-[12.5px] font-semibold text-ink-500 hover:text-wa-deep border-b-2 border-transparent transition">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M2.5 3.5h11v8h-7L4 14V11.5H2.5z" />
                        </svg>
                        Messages
                        <span data-details-tab-count="messages"
                            class="ml-0.5 font-mono text-[10px] text-ink-500"></span>
                    </button>
                    <button data-details-tab="attachments"
                        class="details-tab inline-flex items-center gap-1.5 px-3 py-2.5 text-[12.5px] font-semibold text-ink-500 hover:text-wa-deep border-b-2 border-transparent transition">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M11 5l-5 5a2 2 0 0 0 2.83 2.83l6-6a3.5 3.5 0 0 0-5-5L4 7.5A5 5 0 0 0 11 14.5" />
                        </svg>
                        Files
                        <span data-details-tab-count="attachments"
                            class="ml-0.5 font-mono text-[10px] text-ink-500"></span>
                    </button>
                </div>
            </div>

            {{-- Tab panels --}}
            <div class="flex-1 min-h-0 overflow-y-auto">
                <section data-details-panel="overview" class="details-panel p-5 text-[13px]"></section>
                <section data-details-panel="recipients" class="details-panel hidden p-5"></section>
                <section data-details-panel="messages" class="details-panel hidden p-5"></section>
                <section data-details-panel="attachments" class="details-panel hidden p-5"></section>
            </div>
        </aside>
    </div>

    {{--
 AI assist drawer — slides in from the right when the user clicks
 the AI button in the thread header. Tools list (Summarize / Suggest
 reply / Translate / Rewrite / Tone) on the left, output pane on
 the right. The Send-as-reply button copies the AI output into the
 composer textarea so the operator can edit before sending.
--}}
    <div id="ai-drawer" class="hidden fixed inset-0 z-50">
        <div id="ai-backdrop" class="absolute inset-0 bg-[rgba(11,31,28,0.46)] transition-opacity"></div>
        <aside id="ai-panel"
            class="absolute right-0 top-0 h-full w-full max-w-[640px] bg-paper-0 border-l border-paper-200 shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] flex flex-col translate-x-full transition-transform duration-200">

            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3 shrink-0">
                <div class="flex items-start gap-3 min-w-0">
                    <span
                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-wa-mint to-wa-bubble grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-5 h-5 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path
                                d="M8 2.5L9.4 6 13 7l-3.6 1L8 11.5 6.6 8 3 7l3.6-1zM12 11.5l.6 1.4L14 13.5l-1.4.6L12 15.5l-.6-1.4L10 13.5l1.4-.6z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('AI assist') }}</div>
                        <h3 class="font-serif text-[22px] leading-tight tracking-[-0.01em] truncate">
                            {{ __('Conversation tools') }}</h3>
                        <div class="mt-0.5 text-[12px] text-ink-500" id="ai-thread-name">
                            {{ __('Pick a thread first') }}</div>
                    </div>
                </div>
                <button id="ai-close" type="button"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center shrink-0"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 min-h-0 grid grid-cols-1 sm:grid-cols-[200px_1fr]">
                <nav class="border-r border-paper-200 bg-paper-50 p-3 space-y-1 overflow-y-auto">
                    @foreach ([['summary', 'Summarize', 'M3 4h10M3 8h10M3 12h6'], ['suggest', 'Suggest reply', 'M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z'], ['rewrite', 'Rewrite tone', 'M11 2l3 3-8 8H3v-3l8-8z'], ['translate', 'Translate', 'M3 5h6M5 3v2c0 3 -2 5 -2 5M5 9c0 0 2 2 5 2M9 9l3 6 3-6M10 12h4'], ['extract', 'Extract info', 'M3 3h10v3H3zM3 8h10M3 12h6'], ['tone', 'Tone analysis', 'M2 8a6 6 0 1 0 12 0 6 6 0 0 0-12 0zM6 9c.5 1 1.5 1.5 2 1.5s1.5-.5 2-1.5M6 6.5h.01M10 6.5h.01']] as [$key, $label, $icon])
                        <button type="button" data-ai-tool="{{ $key }}"
                            class="ai-tool w-full text-left px-3 py-2 rounded-xl text-[12.5px] font-medium text-ink-700 hover:bg-paper-0 flex items-center gap-2 [&.active]:bg-wa-deep [&.active]:text-paper-0">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="{{ $icon }}" />
                            </svg>
                            {{ $label }}
                        </button>
                    @endforeach

                    <div class="pt-3 mt-3 border-t border-paper-200">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 px-3 mb-1">
                            {{ __('Model') }}</div>
                        <select id="ai-model"
                            class="w-full px-2.5 py-1.5 rounded-lg border border-paper-200 bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                            @foreach (\App\Http\Controllers\Admin\AdminAiKeyController::MODELS as $providerKey => $modelList)
                                <optgroup label="{{ ucfirst($providerKey) }}">
                                    @foreach ($modelList as $m)
                                        <option value="{{ $m }}">{{ $m }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </nav>

                <section class="p-5 flex flex-col min-h-0 overflow-hidden">
                    {{-- Optional input area for translate/rewrite/tone where the
 user supplies extra context (target language, target tone,
 etc.). Hidden by default; the JS shows it for those tools. --}}
                    <div id="ai-input-row" class="hidden mb-3">
                        <label class="text-[10.5px] font-mono uppercase tracking-[0.14em] text-ink-500"
                            id="ai-input-label">{{ __('Option') }}</label>
                        <input type="text" id="ai-input"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <button id="ai-run" type="button"
                            class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M5 3l8 5-8 5z" />
                            </svg>
                            Run
                        </button>
                        <button id="ai-copy" type="button"
                            class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium"
                            disabled>{{ __('Copy') }}</button>
                        <button id="ai-use" type="button"
                            class="px-3 py-1.5 rounded-full border border-wa-deep/40 bg-wa-mint text-wa-deep hover:bg-wa-bubble text-[12px] font-semibold inline-flex items-center gap-1.5"
                            disabled>
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                            Use as reply
                        </button>
                        <span id="ai-status" class="ml-auto text-[11px] font-mono text-ink-500"></span>
                    </div>

                    <div id="ai-output"
                        class="flex-1 min-h-0 overflow-y-auto p-4 rounded-xl border border-paper-200 bg-paper-50 text-[13px] leading-relaxed text-ink-700">
                        <div class="text-ink-500 text-[12.5px]">{{ __('Pick a tool on the left, then hit') }}
                            <b>Run</b>. AI uses the most recent messages on this thread for context.</div>
                    </div>

                    <div
                        class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between text-[10.5px] font-mono text-ink-500">
                        <span>{{ __('AI is suggestion-only. Review before sending.') }}</span>
                        <span>{{ __('Last 30 messages used as context') }}</span>
                    </div>
                </section>
            </div>
        </aside>
    </div>

    <div id="schedule-modal"
        class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
        <div
            class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Scheduled send') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight tracking-[-0.01em]">
                        {{ __('Pick a date & time') }}</h3>
                </div>
                <button id="schedule-modal-close" type="button"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="block">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Date') }}</span>
                    <input id="schedule-date" type="date"
                        class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                </label>
                <label class="block">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Time') }}</span>
                    <input id="schedule-time" type="time"
                        class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                </label>
                <label class="block col-span-2">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Timezone') }}</span>
                    <select id="schedule-timezone"
                        class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                        @php
                            $userTz =
                                optional(auth()->user()?->currentWorkspace)->timezone ??
                                (auth()->user()?->timezone ?? config('app.timezone', 'UTC'));
                        @endphp
                        @foreach (\DateTimeZone::listIdentifiers() as $tz)
                            <option value="{{ $tz }}" {{ $tz === $userTz ? 'selected' : '' }}>
                                {{ $tz }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="px-5 pb-5 pt-1 flex items-center justify-between gap-2">
                <button id="schedule-clear" type="button"
                    class="text-[12px] font-semibold text-ink-500 hover:text-wa-deep">{{ __('Clear') }}</button>
                <div class="flex gap-2">
                    <button id="schedule-cancel" type="button"
                        class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">{{ __('Cancel') }}</button>
                    <button id="schedule-save" type="button"
                        class="px-4 py-2 rounded-full bg-ink-900 text-paper-0 text-[12px] font-semibold hover:bg-wa-deep">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div id="location-modal"
        class="hidden fixed inset-0 z-50 items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
        <div
            class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Location') }}
                    </div>
                    <h3 class="font-serif text-[22px] leading-tight tracking-[-0.01em]">
                        {{ __('Send a location pin') }}</h3>
                </div>
                <button id="location-modal-close" type="button"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-white hover:bg-paper-50 grid place-items-center"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>
            <div class="p-5 grid gap-3">
                <button id="location-use-current" type="button"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl border border-wa-deep/30 bg-wa-mint/40 text-[13px] font-semibold hover:bg-wa-mint">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <path d="M8 2.5a5 5 0 0 0-5 5c0 3.8 5 7 5 7s5-3.2 5-7a5 5 0 0 0-5-5Z" />
                        <circle cx="8" cy="7.5" r="1.6" />
                    </svg>
                    Use my current location
                </button>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="block">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Latitude') }}</span>
                        <input id="location-lat" type="number" step="0.0000001"
                            class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep"
                            placeholder="e.g. 28.6139">
                    </label>
                    <label class="block">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Longitude') }}</span>
                        <input id="location-lng" type="number" step="0.0000001"
                            class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep"
                            placeholder="e.g. 77.2090">
                    </label>
                </div>
                <div id="location-error" class="hidden text-[12px] text-accent-coral"></div>
            </div>
            <div class="px-5 pb-5 pt-1 flex justify-end gap-2">
                <button id="location-cancel" type="button"
                    class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">{{ __('Cancel') }}</button>
                <button id="location-send" type="button"
                    class="px-4 py-2 rounded-full bg-ink-900 text-paper-0 text-[12px] font-semibold hover:bg-wa-deep">{{ __('Send pin') }}</button>
            </div>
        </div>
    </div>

    <div id="confirm-modal"
        class="hidden fixed inset-0 z-[60] items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
        <div
            class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
            <div class="px-5 py-4 flex items-start gap-3 border-b border-paper-200">
                <div id="confirm-icon"
                    class="w-10 h-10 rounded-2xl grid place-items-center bg-accent-coral/15 text-accent-coral shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div id="confirm-eyebrow" class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Confirm') }}</div>
                    <h3 id="confirm-title" class="font-serif text-[20px] leading-tight tracking-[-0.01em] mt-0.5">
                        {{ __('Are you sure?') }}</h3>
                    <p id="confirm-message" class="mt-1.5 text-[13px] text-ink-600 leading-relaxed"></p>
                </div>
            </div>
            <div class="px-5 py-3 flex justify-end gap-2 bg-paper-50/60">
                <button id="confirm-cancel" type="button"
                    class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">{{ __('Cancel') }}</button>
                <button id="confirm-ok" type="button"
                    class="px-4 py-2 rounded-full bg-accent-coral text-paper-0 text-[12px] font-semibold hover:bg-[#A1431F]">{{ __('Delete') }}</button>
            </div>
        </div>
    </div>

    <div id="toast"
        class="hidden fixed bottom-6 right-7 z-50 rounded-[14px] border border-wa-deep bg-paper-0 shadow-soft px-4 py-3 text-[13px] font-semibold">
    </div>

    <script id="chat-state" type="application/json">@json($chatState)</script>

    {{-- Localise the JS-rendered chat UI (empty states, toasts, placeholders, …). --}}
    @include('partials.js-i18n')

    @php
        $tlmWsId = (int) (auth()->user()->current_workspace_id ?? 0);
        $tlmAttrs = $tlmWsId ? app(\App\Services\SendAttributes::class)->catalog($tlmWsId) : [];
    @endphp
    {{-- Click-to-insert attribute catalog read by the shared send-time
         mapping panel. Published as JSON so operator-authored labels and
         sample values cannot break out into code. --}}
    <script>
        window.__tlmAttributes = @json($tlmAttrs);
    </script>

</x-layouts.user>

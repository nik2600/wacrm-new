<x-layouts.user :title="__('Support')" nav-key="more" page="user-support-index">

    <!-- Sub header -->
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/more') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to More') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Support') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Talk to') }} <span
                            class="italic text-wa-deep">{{ __('support') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Live support inbox</span>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-4">
            <div
                class="rounded-lg border border-wa-green/40 bg-wa-mint px-4 py-2.5 text-[12.5px] text-wa-deep font-mono flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M3 8l3 3 7-8" />
                </svg>
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-4">
            <div
                class="rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12.5px] text-[#A1431F]">
                @foreach ($errors->all() as $err)
                    <div class="font-mono">{{ $err }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <div class="grid grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)] gap-5 items-start">

            <!-- Reasons sidebar -->
            <aside
                class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden lg:sticky lg:top-[20px]">
                <div class="px-4 py-3 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __("What's it about") }}</div>
                    <h2 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Pick a reason') }}</h2>
                </div>
                <ul id="reasons" class="divide-y divide-paper-200">
                    <li>
                        <button type="button"
                            class="reason w-full text-left px-4 py-3 flex items-start gap-3 transition hover:bg-paper-50/60 bg-paper-50/40 border-l-[3px] border-wa-deep"
                            data-reason="bug">
                            <span
                                class="w-9 h-9 rounded-xl bg-accent-coral/15 text-[#A1431F] grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <circle cx="8" cy="8" r="3.5" />
                                    <path
                                        d="M8 4.5V2M8 14v-2.5M4.5 8H2M14 8h-2.5M5 5l-1.5-1.5M11 5l1.5-1.5M5 11l-1.5 1.5M11 11l1.5 1.5" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold">{{ __('Something is broken') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">
                                    {{ __('Bugs, errors, or unexpected behavior in the app.') }}</div>
                            </div>
                        </button>
                    </li>
                    <li>
                        <button type="button"
                            class="reason w-full text-left px-4 py-3 flex items-start gap-3 transition hover:bg-paper-50/60 border-l-[3px] border-transparent"
                            data-reason="delivery">
                            <span
                                class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M2 8h10M8 4l4 4-4 4M14 3v10" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold">{{ __('Message delivery') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">
                                    {{ __('Messages not landing, stuck in queue, or delayed.') }}</div>
                            </div>
                        </button>
                    </li>
                    <li>
                        <button type="button"
                            class="reason w-full text-left px-4 py-3 flex items-start gap-3 transition hover:bg-paper-50/60 border-l-[3px] border-transparent"
                            data-reason="template">
                            <span
                                class="w-9 h-9 rounded-xl bg-[#D9E5F2] text-[#13478A] grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="2.5" y="2.5" width="11" height="11" rx="1.5" />
                                    <path d="M2.5 6h11M6 13.5V6" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold">{{ __('Template approval') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">
                                    {{ __('Rejected, paused, or stuck-in-review templates.') }}</div>
                            </div>
                        </button>
                    </li>
                    <li>
                        <button type="button"
                            class="reason w-full text-left px-4 py-3 flex items-start gap-3 transition hover:bg-paper-50/60 border-l-[3px] border-transparent"
                            data-reason="billing">
                            <span
                                class="w-9 h-9 rounded-xl bg-accent-amber/20 text-[#7B5A14] grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="2" y="4" width="12" height="9" rx="1.5" />
                                    <path d="M2 7h12" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold">{{ __('Billing & plans') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">
                                    {{ __('Invoices, upgrades, refunds, payment methods.') }}</div>
                            </div>
                        </button>
                    </li>
                    <li>
                        <button type="button"
                            class="reason w-full text-left px-4 py-3 flex items-start gap-3 transition hover:bg-paper-50/60 border-l-[3px] border-transparent"
                            data-reason="integration">
                            <span
                                class="w-9 h-9 rounded-xl bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M5.5 4.5 3 7l2.5 2.5M10.5 4.5 13 7l-2.5 2.5M7 12l2-8" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold">{{ __('Integration help') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">
                                    {{ __('API keys, webhooks, Shopify, Zapier, custom code.') }}</div>
                            </div>
                        </button>
                    </li>
                    <li>
                        <button type="button"
                            class="reason w-full text-left px-4 py-3 flex items-start gap-3 transition hover:bg-paper-50/60 border-l-[3px] border-transparent"
                            data-reason="account">
                            <span
                                class="w-9 h-9 rounded-xl bg-[#E8F5E9] text-wa-deep grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <circle cx="8" cy="6" r="3" />
                                    <path d="M3 14c0-3 2.5-5 5-5s5 2 5 5" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold">{{ __('Account access') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">
                                    {{ __('Login issues, 2FA, seats, role changes.') }}</div>
                            </div>
                        </button>
                    </li>
                    <li>
                        <button type="button"
                            class="reason w-full text-left px-4 py-3 flex items-start gap-3 transition hover:bg-paper-50/60 border-l-[3px] border-transparent"
                            data-reason="other">
                            <span
                                class="w-9 h-9 rounded-xl bg-paper-100 text-ink-700 grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <circle cx="3.5" cy="8" r="1" />
                                    <circle cx="8" cy="8" r="1" />
                                    <circle cx="12.5" cy="8" r="1" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold">{{ __('Something else') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5 leading-snug">
                                    {{ __('Feature requests, feedback, anything else.') }}</div>
                            </div>
                        </button>
                    </li>
                </ul>
            </aside>

            <!-- Form -->
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <div class="px-6 py-5 border-b border-paper-200 flex items-start justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500" id="cat-label">
                            {{ __('Something is broken') }}</div>
                        <h1 class="font-serif text-[26px] leading-tight tracking-[-0.01em] mt-0.5">
                            {{ __('How can we help?') }}</h1>
                        <p class="mt-1 text-[12.5px] text-ink-500 max-w-[520px] leading-snug">
                            {{ __('Drop the details below and our team will get back within a few hours during business hours.') }}
                        </p>
                    </div>
                </div>

                <form id="supportForm" method="POST" action="{{ route('user.support.store') }}"
                    class="p-6 space-y-5">
                    @csrf
                    {{-- The reason buttons in the left rail flip `border-wa-deep`
 on the active <button>. We mirror the active one into this
 hidden input so a real POST carries the selection. Defaults
 to the first reason (`bug`) — the JS keeps it in sync. --}}
                    <input type="hidden" name="reason" id="sup-reason" value="{{ old('reason', 'bug') }}">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="sup-name">{{ __('Your name') }} <span class="text-accent-coral">*</span></label>
                            <input id="sup-name" name="name" type="text" placeholder="{{ __('Your name') }}"
                                value="{{ old('name', $prefill['name'] ?? '') }}"
                                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                for="sup-email">{{ __('Reply email') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="sup-email" name="email" type="email" placeholder="you@brand.example"
                                value="{{ old('email', $prefill['email'] ?? '') }}"
                                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                        </div>
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                            for="sup-subject">{{ __('Subject') }} <span class="text-accent-coral">*</span></label>
                        <input id="sup-subject" name="subject" type="text" value="{{ old('subject') }}"
                            placeholder="{{ __('One-line summary of the issue') }}"
                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            required>
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                            for="sup-message">{{ __('Tell us what happened') }} <span
                                class="text-accent-coral">*</span></label>
                        <textarea id="sup-message" name="message" rows="7"
                            placeholder="{{ __('Steps you took, what you expected, what actually happened. Paste any error messages or message IDs if you have them.') }}"
                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] resize-y focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            required>{{ old('message') }}</textarea>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Markdown supported. Be as specific as you can — it speeds things up.') }}</div>
                    </div>

                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Screenshots / attachments') }}
                            <span class="text-[10.5px] font-normal text-ink-500">(optional)</span></label>
                        <div id="att-drop"
                            class="border-2 border-dashed border-paper-200 rounded-lg p-6 text-center hover:border-wa-deep transition cursor-pointer"
                            onclick="document.getElementById('att-files').click()">
                            <span
                                class="w-10 h-10 rounded-full bg-paper-50 inline-flex items-center justify-center mb-2"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                    <circle cx="6" cy="7" r="1.2" />
                                    <path d="m3 11 3-3 4 4 3-3 0 4" />
                                </svg></span>
                            <div class="text-[13px] font-semibold">{{ __('Drop files or') }} <span
                                    class="text-wa-deep">{{ __('browse') }}</span></div>
                            <div class="text-[10.5px] text-ink-500 font-mono mt-1">
                                {{ __('Image · PDF · TXT · LOG / max 10 MB each') }}</div>
                            <input id="att-files" type="file" class="hidden" accept="image/*,.pdf,.txt,.log,.csv"
                                multiple onchange="handleAtt(this)">
                        </div>
                        <div id="att-list" class="mt-3 space-y-2 hidden"></div>
                    </div>

                    <div class="pt-3 border-t border-paper-200 flex items-center justify-between gap-3 flex-wrap">
                        <div class="text-[11.5px] text-ink-500">
                            {{ __('By submitting, you agree to share account context with our support team to debug your issue.') }}
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                            <button type="submit"
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M2 8h10M8 4l4 4-4 4" />
                                </svg>
                                Send to support
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Your recent tickets — driven by the `$tickets` collection that
 SupportTicketController::index() passes. Lives in the right
 column under the form so a freshly-submitted ticket pops up
 immediately. --}}
            @if (!empty($tickets) && count($tickets) > 0)
                <div class="xl:col-start-2 bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                    <div class="px-6 py-4 border-b border-paper-200 flex items-end justify-between gap-3">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Your history') }}</div>
                            <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Recent tickets') }}</h2>
                        </div>
                        <a href="{{ url('/account?tab=support') }}"
                            class="text-[11.5px] font-semibold text-wa-deep hover:underline">{{ __('Full history →') }}</a>
                    </div>
                    <div class="divide-y divide-paper-200">
                        @foreach ($tickets as $t)
                            @php
                                $pill = match ($t->status) {
                                    'awaiting_user' => [
                                        'bg' => 'bg-accent-amber/15',
                                        'text' => 'text-[#7B5A14]',
                                        'border' => 'border border-accent-amber/40',
                                        'label' => 'your turn',
                                    ],
                                    'awaiting_support' => [
                                        'bg' => 'bg-wa-mint',
                                        'text' => 'text-wa-deep',
                                        'border' => 'border border-wa-green/40',
                                        'label' => 'awaiting reply',
                                    ],
                                    'resolved' => [
                                        'bg' => 'bg-paper-50',
                                        'text' => 'text-ink-700',
                                        'border' => 'border border-paper-200',
                                        'label' => 'resolved',
                                    ],
                                    default => [
                                        'bg' => 'bg-wa-mint',
                                        'text' => 'text-wa-deep',
                                        'border' => 'border border-wa-green/40',
                                        'label' => 'open',
                                    ],
                                };
                                $isNew = session('new_ticket_number') === $t->ticket_number;
                            @endphp
                            <a href="{{ route('user.support.show', $t->id) }}"
                                class="block px-6 py-4 hover:bg-paper-50/40 transition {{ $isNew ? 'bg-wa-mint/30' : '' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                                            <span
                                                class="font-mono text-[10.5px] text-ink-500">#{{ $t->ticket_number }}</span>
                                            <span
                                                class="inline-flex items-center gap-1 text-[10.5px] font-mono px-2 py-0.5 rounded-full {{ $pill['bg'] }} {{ $pill['text'] }} {{ $pill['border'] }}">{{ $pill['label'] }}</span>
                                            @if ($isNew)
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-mono px-1.5 py-0.5 rounded bg-wa-green/15 text-wa-deep border border-wa-green/30">{{ __('just submitted') }}</span>
                                            @endif
                                        </div>
                                        <h4 class="font-serif text-[16px] leading-tight">{{ $t->subject }}</h4>
                                        <p class="text-[12px] text-ink-500 mt-1 line-clamp-2">
                                            {{ Str::limit($t->message, 140) }}</p>
                                    </div>
                                    <span
                                        class="font-mono text-[10.5px] text-ink-500 shrink-0">{{ $t->created_at->format('M d') }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </main>

    <script>
        // Keep the hidden #sup-reason input in sync with whichever reason
        // pill the operator clicked in the left rail. The pill JS isn't ours
        // (lives elsewhere); we just listen for clicks on `.reason` buttons.
        (function() {
            const reasonField = document.getElementById('sup-reason');
            if (!reasonField) return;
            document.querySelectorAll('button.reason[data-reason]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const r = btn.getAttribute('data-reason');
                    if (r) reasonField.value = r;
                });
            });
        })();
    </script>

</x-layouts.user>

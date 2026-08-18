<x-layouts.user :title="__('Appointments')" nav-key="more" page="user-appointments-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            {{-- Left rail --}}
            <aside class="space-y-3">
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center" style="background:#E8F0FE">
                        <svg viewBox="0 0 32 32" class="w-7 h-7">
                            <rect x="6" y="9" width="20" height="17" rx="2" fill="#4285F4" />
                            <rect x="6" y="9" width="20" height="5" fill="#1A73E8" />
                            <rect x="9" y="6" width="2" height="6" rx="1" fill="#1A73E8" />
                            <rect x="21" y="6" width="2" height="6" rx="1" fill="#1A73E8" />
                            <text x="16" y="22" text-anchor="middle" fill="#fff" font-family="Arial" font-size="9"
                                font-weight="bold">31</text>
                        </svg>
                    </div>
                    <div class="font-serif text-[18px] leading-tight">{{ __('Appointments') }}</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">
                        {{ __('Google Calendar') }}</div>
                    <div
                        class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono {{ $isConnected ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-50 text-ink-700 border border-paper-200' }}">
                        <span
                            class="w-1.5 h-1.5 rounded-full {{ $isConnected ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                        {{ $isConnected ? 'Connected' : 'Not connected' }}
                    </div>
                </div>

                @if ($isConnected)
                    <div
                        class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card text-[12px] text-ink-700">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Booking rules') }}</div>
                        <ul class="space-y-1.5 text-[12px]">
                            <li class="flex items-center justify-between"><span>{{ __('Slot duration') }}</span><span
                                    class="font-mono text-[11px] text-ink-500">{{ $settings['slot_duration_minutes'] ?? 30 }}
                                    {{ __('min') }}</span></li>
                            <li class="flex items-center justify-between"><span>{{ __('Buffer') }}</span><span
                                    class="font-mono text-[11px] text-ink-500">{{ $settings['buffer_before_minutes'] ?? 0 }}
                                    / {{ $settings['buffer_after_minutes'] ?? 0 }} {{ __('min') }}</span></li>
                            <li class="flex items-center justify-between"><span>{{ __('Max per day') }}</span><span
                                    class="font-mono text-[11px] text-ink-500">{{ $settings['max_per_day'] ?? 16 }}</span>
                            </li>
                            <li class="flex items-center justify-between"><span>{{ __('Advance window') }}</span><span
                                    class="font-mono text-[11px] text-ink-500">{{ $settings['advance_days'] ?? 14 }}
                                    {{ __('days') }}</span></li>
                            <li class="flex items-center justify-between"><span>{{ __('Reminder') }}</span><span
                                    class="font-mono text-[11px] text-ink-500">{{ $settings['reminder_minutes_before'] ?? 60 }}
                                    {{ __('min') }}</span></li>
                        </ul>
                    </div>
                @else
                    <div
                        class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card text-[12px] text-ink-700">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('What this does') }}</div>
                        <ul class="space-y-1.5">
                            <li class="flex items-start gap-2"><span class="text-wa-deep">✓</span> Customers book slots
                                inside WhatsApp</li>
                            <li class="flex items-start gap-2"><span class="text-wa-deep">✓</span> Reads free/busy from
                                your calendar</li>
                            <li class="flex items-start gap-2"><span class="text-wa-deep">✓</span> Writes events back
                                automatically</li>
                        </ul>
                    </div>
                @endif
            </aside>

            {{-- Main --}}
            <section class="space-y-5">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        <a href="{{ url('/integrations') }}" class="hover:text-wa-deep">{{ __('Integrations') }}</a>
                        <span class="mx-1.5 text-ink-500/60">/</span>
                        <span>{{ __('Appointments') }}</span>
                    </div>
                    @if ($isConnected)
                        <div class="flex items-end justify-between gap-4 flex-wrap">
                            <div>
                                <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">
                                    {{ __('Upcoming') }} <span class="italic text-wa-deep">{{ __('bookings') }}</span>
                                </h1>
                                <p class="text-[13px] text-ink-600 mt-2">{{ __('Calendar:') }} <span
                                        class="font-mono">{{ $settings['google_oauth']['calendar_name'] ?? '—' }}</span>
                                    · {{ $upcoming->count() }} {{ __('upcoming') }}</p>
                            </div>
                            <a href="{{ url('/appointments/settings') }}"
                                class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="8" cy="8" r="2" />
                                    <path
                                        d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z" />
                                </svg>
                                Settings
                            </a>
                        </div>
                    @else
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('Connect') }}
                            <span class="italic text-wa-deep">{{ __('Google Calendar') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __("Let customers book appointments inside WhatsApp. :app reads your calendar's availability and writes confirmed bookings back as events.", ['app' => brand_name()]) }}
                        </p>
                    @endif
                </div>

                @if (session('success'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div
                        class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F]">
                        {{ session('error') }}</div>
                @endif

                @if (!$isConnected)
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h2 class="font-serif text-[22px] leading-tight mb-3">{{ __('Set up booking') }}</h2>
                        <p class="text-[12.5px] text-ink-600 mb-4 max-w-xl">
                            {{ __('Connect a Google Calendar, pick your availability windows, and drop a "Book Appointment" node into any flow. Customers pick a slot from a WhatsApp list message — :app writes it to your calendar.', ['app' => brand_name()]) }}
                        </p>
                        <a href="{{ url('/appointments/settings') }}"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                            Set up booking
                        </a>
                    </div>
                @else
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <h2 class="font-serif text-[22px] leading-tight">{{ __('Upcoming') }} <span
                                    class="font-mono text-[11px] text-ink-500 ml-2">{{ $upcoming->count() }}</span>
                            </h2>
                        </div>
                        @if ($upcoming->isEmpty())
                            <div class="px-5 py-12 text-center">
                                <div class="font-serif text-[18px]">{{ __('No upcoming bookings') }}</div>
                                <p class="text-[12.5px] text-ink-600 mt-1">{{ __('Add a') }} <span
                                        class="font-mono text-[11px]">{{ __('Book appointment') }}</span> node to any
                                    flow — customers will be able to book from here.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px]">
                                <thead class="bg-paper-50 text-left font-mono text-[10.5px] uppercase text-ink-500">
                                    <tr>
                                        <th class="px-4 py-2.5">{{ __('When') }}</th>
                                        <th class="px-4 py-2.5">{{ __('Customer') }}</th>
                                        <th class="px-4 py-2.5">{{ __('Title') }}</th>
                                        <th class="px-4 py-2.5">{{ __('Status') }}</th>
                                        <th class="px-4 py-2.5 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-100">
                                    @foreach ($upcoming as $a)
                                        @php
                                            $statusCss = match ($a->status) {
                                                'confirmed' => 'bg-wa-green/15 text-wa-deep',
                                                'pending' => 'bg-accent-amber/20 text-accent-amber',
                                                default => 'bg-paper-100 text-ink-700',
                                            };
                                        @endphp
                                        <tr class="hover:bg-paper-50">
                                            <td class="px-4 py-3">
                                                <div class="font-serif text-[14px]">
                                                    {{ $a->starts_at?->setTimezone($a->timezone)->format('D j M') }}
                                                </div>
                                                <div class="font-mono text-[10.5px] text-ink-500">
                                                    {{ $a->starts_at?->setTimezone($a->timezone)->format('g:i A') }} –
                                                    {{ $a->ends_at?->setTimezone($a->timezone)->format('g:i A') }}
                                                    {{ $a->timezone }}</div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-[12.5px]">{{ $a->meta['customer_name'] ?? '—' }}
                                                </div>
                                                <div class="font-mono text-[10.5px] text-ink-500">
                                                    {{ $a->meta['customer_phone'] ?? '' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-ink-700">{{ $a->title }}</td>
                                            <td class="px-4 py-3"><span
                                                    class="font-mono text-[10px] uppercase px-2 py-0.5 rounded-full {{ $statusCss }}">{{ $a->status }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                @if (in_array($a->status, ['pending', 'confirmed']))
                                                    <form method="POST"
                                                        action="{{ url('/appointments/' . $a->id . '/cancel') }}"
                                                        onsubmit="return confirm('Cancel this appointment?')">
                                                        @csrf
                                                        <button
                                                            class="px-2.5 py-1 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[10.5px] font-mono">{{ __('Cancel') }}</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        @endif
                    </div>

                    @if ($past->isNotEmpty())
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200">
                                <h2 class="font-serif text-[20px] leading-tight">{{ __('Recent past') }} <span
                                        class="font-mono text-[11px] text-ink-500 ml-2">{{ $past->count() }}</span>
                                </h2>
                            </div>
                            <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px]">
                                <tbody class="divide-y divide-paper-100">
                                    @foreach ($past->take(10) as $p)
                                        <tr class="hover:bg-paper-50">
                                            <td class="px-4 py-2.5 font-mono text-[11px] text-ink-500 w-[180px]">
                                                {{ $p->starts_at?->setTimezone($p->timezone)->format('D j M, g:i A') }}
                                            </td>
                                            <td class="px-4 py-2.5">{{ $p->meta['customer_name'] ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-ink-700">{{ $p->title }}</td>
                                            <td class="px-4 py-2.5 text-right font-mono text-[10.5px] text-ink-500">
                                                {{ $p->status }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    @endif
                @endif
            </section>
        </div>
    </main>

</x-layouts.user>

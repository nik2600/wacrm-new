@php
    $days = [
        'mon' => 'Mon',
        'tue' => 'Tue',
        'wed' => 'Wed',
        'thu' => 'Thu',
        'fri' => 'Fri',
        'sat' => 'Sat',
        'sun' => 'Sun',
    ];
    $windows = $settings['availability_windows'] ?? $defaultWindows;
@endphp
<x-layouts.user :title="__('Appointment settings')" nav-key="more" page="user-appointments-settings">

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
                    <div class="font-serif text-[18px] leading-tight">{{ __('Google Calendar') }}</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">
                        {{ __('Appointment booking') }}</div>
                    <div
                        class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono {{ $isConnected ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-50 text-ink-700 border border-paper-200' }}">
                        <span
                            class="w-1.5 h-1.5 rounded-full {{ $isConnected ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                        {{ $isConnected ? 'Connected' : 'Not connected' }}
                    </div>
                </div>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card text-[12px] text-ink-700">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('What this does') }}</div>
                    <ul class="space-y-1.5">
                        <li class="flex items-start gap-2"><span class="text-wa-deep">✓</span> Reads free/busy intervals
                            from your calendar</li>
                        <li class="flex items-start gap-2"><span class="text-wa-deep">✓</span> Offers next free slots as
                            WhatsApp list message</li>
                        <li class="flex items-start gap-2"><span class="text-wa-deep">✓</span> Writes confirmed bookings
                            back as events</li>
                        <li class="flex items-start gap-2"><span class="text-wa-deep">✓</span> Refresh-token rotated,
                            encrypted at rest</li>
                    </ul>
                </div>

                @if ($isConnected)
                    <div
                        class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card text-[12px] text-ink-700">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Quick links') }}</div>
                        <ul class="space-y-1.5">
                            <li><a href="{{ url('/appointments') }}" class="text-wa-deep hover:underline">→ Upcoming
                                    bookings</a></li>
                            <li><a href="{{ url('/flows') }}" class="text-wa-deep hover:underline">→ Add "Book
                                    appointment" to a flow</a></li>
                            <li><a href="{{ url('/integrations') }}" class="text-wa-deep hover:underline">→ All
                                    integrations</a></li>
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
                        <a href="{{ url('/appointments') }}" class="hover:text-wa-deep">{{ __('Appointments') }}</a>
                        <span class="mx-1.5 text-ink-500/60">/</span>
                        <span>{{ __('Settings') }}</span>
                    </div>
                    @if ($isConnected)
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('Booking') }}
                            <span class="italic text-wa-deep">{{ __('settings') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">{{ __('Calendar:') }} <span
                                class="font-mono">{{ $oauth['calendar_name'] ?? ($oauth['calendar_id'] ?? '— pick below —') }}</span>
                            · Connected
                            {{ \Illuminate\Support\Carbon::parse($oauth['connected_at'] ?? null)?->diffForHumans() ?? '—' }}
                        </p>
                    @else
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('Connect') }}
                            <span class="italic text-wa-deep">{{ __('Google Calendar') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __("Let customers book appointments inside WhatsApp. :app reads your calendar's free/busy intervals and writes confirmed bookings back as calendar events.", ['app' => brand_name()]) }}
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
                    @if (!$appEnabled)
                        <div
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start gap-5">
                            <div class="w-12 h-12 rounded-xl bg-accent-amber/20 grid place-items-center shrink-0">
                                <svg viewBox="0 0 24 24" class="w-6 h-6 text-accent-amber" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path d="M12 9v3M12 16h.01" />
                                    <circle cx="12" cy="12" r="9" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-serif text-[22px] leading-tight">
                                    {{ __("Google Calendar isn't configured yet") }}</div>
                                <p class="text-[12.5px] text-ink-600 mt-1.5 max-w-2xl">
                                    An admin needs to register a Google Cloud OAuth client at <span
                                        class="font-mono">{{ __('console.cloud.google.com') }}</span> and paste the
                                    Client ID + Client Secret at <span
                                        class="font-mono text-wa-deep">/admin/settings/google-calendar</span>.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                            <h2 class="font-serif text-[22px] leading-tight mb-3">
                                {{ __('Connect your Google Calendar') }}</h2>
                            <p class="text-[12.5px] text-ink-600 mb-4">
                                You'll be redirected to Google to approve calendar access. We request:
                                <span
                                    class="font-mono text-[11.5px] text-ink-700 block mt-1">https://www.googleapis.com/auth/calendar</span>
                            </p>
                            <form method="POST" action="{{ url('/appointments/oauth/google/start') }}">
                                @csrf
                                <button type="submit"
                                    class="px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.7">
                                        <path d="M3 8h10M9 4l4 4-4 4" />
                                    </svg>
                                    Connect Google Calendar
                                </button>
                            </form>
                        </div>
                    @endif
                @else
                    {{-- Connected: full settings form --}}
                    <form method="POST" action="{{ url('/appointments/settings') }}" class="space-y-5">
                        @csrf

                        {{-- Calendar picker --}}
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Step 1') }}</div>
                                    <h2 class="font-serif text-[20px] leading-tight">
                                        {{ __('Which calendar to book into') }}</h2>
                                </div>
                                {{-- Disconnect submits an EXTERNAL form (defined after the
                                     settings form) via form="…". A real <form> here would be
                                     nested inside the settings form — invalid HTML that makes
                                     the browser close the settings form early, orphaning the
                                     "Save settings" button so clicking it did nothing. --}}
                                <button type="submit" form="googleDisconnectForm"
                                    class="px-3 py-1.5 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[11.5px] font-semibold">{{ __('Disconnect') }}</button>
                            </div>
                            <div class="p-5">
                                @if (empty($calendars))
                                    <p class="text-[12.5px] text-ink-600">{{ __('No writable calendars returned.') }}
                                        <button type="button" onclick="location.reload()"
                                            class="text-wa-deep underline">{{ __('Refresh') }}</button></p>
                                @else
                                    <select name="calendar_id" id="calendar_id_select"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        @foreach ($calendars as $cal)
                                            <option value="{{ $cal['id'] }}"
                                                data-name="{{ $cal['summary'] ?? '' }}"
                                                data-tz="{{ $cal['timeZone'] ?? '' }}" @selected(($oauth['calendar_id'] ?? '') === $cal['id'])>
                                                {{ $cal['summary'] ?? $cal['id'] }} @if (!empty($cal['primary']))
                                                    (primary)
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="calendar_name" id="calendar_name"
                                        value="{{ $oauth['calendar_name'] ?? '' }}">
                                    <input type="hidden" name="calendar_timezone" id="calendar_timezone"
                                        value="{{ $oauth['calendar_timezone'] ?? '' }}">
                                    <p class="text-[11px] text-ink-500 font-mono mt-2">
                                        {{ __("Tip: pick a dedicated calendar so personal events don't mix with bookings.") }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        {{-- Availability windows --}}
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Step 2') }}</div>
                                <h2 class="font-serif text-[20px] leading-tight">{{ __('When can customers book') }}
                                </h2>
                                <p class="text-[12px] text-ink-500 mt-0.5">
                                    {{ __('One row per weekday. Leave blank to disable that day entirely.') }}</p>
                            </div>
                            <div class="p-5 space-y-2">
                                @foreach ($days as $key => $label)
                                    @php $win = $windows[$key][0] ?? ['from' => '', 'to' => '']; @endphp
                                    <div class="grid grid-cols-[64px_1fr_auto_1fr] lg:grid-cols-[80px_1fr_24px_1fr] gap-2 sm:gap-3 items-center">
                                        <div class="font-mono text-[11px] uppercase tracking-wider text-ink-700">
                                            {{ $label }}</div>
                                        <input type="time"
                                            name="availability_windows[{{ $key }}][0][from]"
                                            value="{{ $win['from'] ?? '' }}"
                                            class="min-w-0 px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                        <div class="text-center text-ink-500">–</div>
                                        <input type="time" name="availability_windows[{{ $key }}][0][to]"
                                            value="{{ $win['to'] ?? '' }}"
                                            class="min-w-0 px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Rules --}}
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Step 3') }}</div>
                                <h2 class="font-serif text-[20px] leading-tight">{{ __('Booking rules') }}</h2>
                            </div>
                            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="text-[12px] text-ink-700">{{ __('Slot duration (minutes)') }} <input
                                        type="number" min="5" max="480" name="slot_duration_minutes"
                                        value="{{ $settings['slot_duration_minutes'] ?? 30 }}"
                                        class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </label>
                                <label class="text-[12px] text-ink-700">{{ __('Max bookings per day') }} <input
                                        type="number" min="1" max="96" name="max_per_day"
                                        value="{{ $settings['max_per_day'] ?? 16 }}"
                                        class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </label>
                                <label class="text-[12px] text-ink-700">{{ __('Buffer before (minutes)') }} <input
                                        type="number" min="0" max="240" name="buffer_before_minutes"
                                        value="{{ $settings['buffer_before_minutes'] ?? 0 }}"
                                        class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </label>
                                <label class="text-[12px] text-ink-700">{{ __('Buffer after (minutes)') }} <input
                                        type="number" min="0" max="240" name="buffer_after_minutes"
                                        value="{{ $settings['buffer_after_minutes'] ?? 0 }}"
                                        class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </label>
                                <label class="text-[12px] text-ink-700">{{ __('Advance booking window (days)') }}
                                    <input type="number" min="1" max="90" name="advance_days"
                                        value="{{ $settings['advance_days'] ?? 14 }}"
                                        class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </label>
                                <label class="text-[12px] text-ink-700">{{ __('Reminder (minutes before)') }} <input
                                        type="number" min="0" max="1440" name="reminder_minutes_before"
                                        value="{{ $settings['reminder_minutes_before'] ?? 60 }}"
                                        class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </label>
                                <label
                                    class="text-[12px] text-ink-700 col-span-2">{{ __('Default location (optional)') }}
                                    <input type="text" maxlength="191" name="default_location"
                                        value="{{ $settings['default_location'] ?? '' }}"
                                        placeholder="{{ __('e.g. 12 MG Road, Bangalore') }}"
                                        class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <a href="{{ url('/appointments') }}"
                                class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12.5px] font-medium">{{ __('Cancel') }}</a>
                            <button type="submit"
                                class="px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Save settings') }}</button>
                        </div>
                    </form>

                    {{-- Google-disconnect form lives OUTSIDE the settings form (the
                         Disconnect button targets it with form="googleDisconnectForm")
                         so the settings form is never nested/broken. --}}
                    <form id="googleDisconnectForm" method="POST"
                        action="{{ url('/appointments/oauth/google/disconnect') }}"
                        onsubmit="return confirm('Disconnect Google Calendar?')" class="hidden">
                        @csrf
                    </form>

                    <script>
                        (function() {
                            const sel = document.getElementById('calendar_id_select');
                            if (!sel) return;
                            const nameEl = document.getElementById('calendar_name');
                            const tzEl = document.getElementById('calendar_timezone');

                            function sync() {
                                const o = sel.selectedOptions[0];
                                if (!o) return;
                                nameEl.value = o.dataset.name || '';
                                tzEl.value = o.dataset.tz || '';
                            }
                            sel.addEventListener('change', sync);
                            sync();
                        })();
                    </script>
                @endif

            </section>
        </div>
    </main>

</x-layouts.user>

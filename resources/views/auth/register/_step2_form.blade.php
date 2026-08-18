{{-- Step 2's form body. Extracted VERBATIM from step2.blade.php so the same
     markup can be dropped into either chrome: <x-auth-shell> for auth variants
     2-5, or the bespoke two-column showcase layout for variant 1 (which is what
     /register itself uses). Nothing here changed - only where it is mounted. --}}
    @php $__brandName = brand_name(); @endphp

    <style>
        .ts-control {
            border-color: rgb(var(--color-paper-200, 230 226 215)) !important;
            border-radius: 0.5rem;
            background: #fff !important;
            font-size: 13px !important;
            padding: 8px 10px !important;
            min-height: 42px !important;
        }
        .ts-wrapper.focus .ts-control { border-color: var(--color-wa-deep) !important; box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-wa-deep) 10%, transparent) !important; }
        .ts-dropdown { font-size: 12.5px; border-radius: 0.5rem; border-color: var(--color-wa-deep); }
        .ts-dropdown .active { background: var(--color-wa-deep); color: #fff; }
    </style>

    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
        {{ __('Step 2 of 3 / Workspace') }}</div>
    <h2 class="font-serif text-[30px] leading-tight tracking-[-0.01em]">{{ __('Create your') }} <span
            class="italic text-wa-deep">{{ __('workspace') }}</span>.</h2>

    <ol class="flex items-center gap-2 mt-3 mb-4 text-[10.5px] font-mono uppercase tracking-wider">
        <li class="text-ink-500 flex items-center gap-1.5"><span
                class="w-5 h-5 rounded-full bg-wa-mint text-wa-deep grid place-items-center text-[10px]">1</span>Account
        </li>
        <li class="w-4 h-px bg-wa-deep"></li>
        <li class="text-wa-deep flex items-center gap-1.5"><span
                class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">2</span>Workspace
        </li>
        <li class="w-4 h-px bg-paper-200"></li>
        <li class="text-ink-500 flex items-center gap-1.5"><span
                class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">3</span>Plan
        </li>
    </ol>

    @if (!empty($existing) && $existing->isNotEmpty())
        <div class="mb-4 rounded-xl bg-paper-50 border border-paper-200 p-3">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                {{ __('Already have') }}</div>
            <div class="space-y-1.5">
                @foreach ($existing as $w)
                    <div class="flex items-center justify-between text-[12.5px]">
                        <span class="font-medium text-ink-900">{{ $w->name }}</span>
                        <span class="font-mono text-[10.5px] text-ink-500">{{ $w->slug }}</span>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('register.plan') }}"
                class="mt-2 inline-flex items-center gap-1 text-[11.5px] text-wa-deep font-semibold hover:underline">
                Skip / continue to plan <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                    stroke="currentColor" stroke-width="1.7">
                    <path d="M3 8h10M9 4l4 4-4 4" />
                </svg>
            </a>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-3 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register.workspace.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Workspace name') }}</label>
            <input required type="text" name="name" maxlength="120"
                value="{{ old('name', $suggested_name ?? '') }}"
                placeholder="{{ __('e.g. Bloomly Marketing') }}"
                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
            <div class="text-[10.5px] text-ink-500 mt-1">
                {{ __('A friendly label your team will see at the top of the app.') }}</div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Industry') }}</label>
                <select name="industry"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    <option value="">{{ __('Select industry') }}</option>
                    @foreach (['ecommerce', 'saas', 'agency', 'education', 'healthcare', 'finance', 'travel', 'hospitality', 'other'] as $opt)
                        <option value="{{ $opt }}" @selected(old('industry') === $opt)>{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Team size') }}</label>
                <select name="size_range"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    <option value="">{{ __('Select team size') }}</option>
                    @foreach (['1', '2-5', '6-20', '21-100', '100+'] as $opt)
                        <option value="{{ $opt }}" @selected(old('size_range') === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block" for="ws-timezone">{{ __('Timezone') }}</label>
            @php $picked = old('timezone', 'Asia/Kolkata'); @endphp
            @php
                // Every IANA timezone (same source campaigns / scheduled / admin use).
                //
                // Built ONCE and cached for a day. It used to construct a
                // DateTimeZone + DateTimeImmutable per row inside the loop â€” ~420
                // object pairs on EVERY render of this page. That is the only heavy
                // thing on step 2 (step 1 has nothing like it), and on a shared host
                // it is enough to blow max_execution_time / memory_limit mid-render:
                // PHP dies with a FATAL (not catchable, nothing in laravel.log) and
                // the browser gets the shell with an EMPTY form panel â€” exactly the
                // "step 1 fine, step 2 blank" symptom.
                $tzOptions = \Illuminate\Support\Facades\Cache::remember('iana_tz_labels_v1', 86400, function () {
                    $out = [];
                    foreach (\DateTimeZone::listIdentifiers() as $tz) {
                        try {
                            $off = (new \DateTimeImmutable('now', new \DateTimeZone($tz)))->format('P');
                        } catch (\Throwable $e) {
                            $off = '';
                        }
                        $out[$tz] = $tz . ($off !== '' ? " (UTC{$off})" : '');
                    }
                    return $out;
                });
            @endphp
            <select id="ws-timezone" name="timezone" class="w-full">
                @foreach ($tzOptions as $tz => $label)
                    <option value="{{ $tz }}" @selected($picked === $tz)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="text-[10.5px] text-ink-500 mt-1">
                {{ __('Type to search any IANA timezone (Asia/Kolkata, Europe/London, etc.).') }}</div>
        </div>

        <button type="submit"
            class="w-full px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2 mt-2">
            {{ __('Continue / pick a plan') }}
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M3 8h10M9 4l4 4-4 4" />
            </svg>
        </button>
        <p class="text-[11.5px] text-ink-500 text-center">
            {{ __('You can create more workspaces any time from the top bar.') }}</p>
    </form>

<x-layouts.admin :title="__('Updater')" admin-key="settings" page="settings-updater">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Updater') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5" id="wd-updater">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Maintenance') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                    {{ __('Application') }} <span class="italic text-wa-deep">{{ __('updater') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Verify your purchase, take a full backup, upload a new version ZIP and run migrations — your .env, database, storage and uploaded images are never touched.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <span class="px-3 py-2 rounded-full bg-paper-100 text-ink-700 text-[11px] font-mono">v{{ $currentVersion }} · build {{ $currentBuild }}</span>
                <a href="{{ url('/admin/settings') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
            </div>
        </div>

        <x-admin.flash />

        <p id="wd-up-banner" class="hidden rounded-2xl px-4 py-3 text-[13px] font-medium"></p>

        {{-- Step 0 — purchase verification --}}
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center gap-3">
                <div data-badge="0" class="w-8 h-8 rounded-full bg-paper-100 text-ink-500 flex items-center justify-center text-[13px] font-bold shrink-0">1</div>
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('step 1 · licence') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-0.5">{{ __('Verify your purchase') }}</h2>
                </div>
            </div>
            <div class="p-5">
                <p class="text-[12.5px] text-ink-600 max-w-2xl">{{ __('Enter your CodeCanyon purchase code. It is validated with Envato before any update can run.') }}</p>
                <div class="mt-3 flex flex-col sm:flex-row gap-2 max-w-xl">
                    <input type="text" id="wd-up-code" value="{{ $verified ? '••••••••-••••-••••-••••-••••••••••••' : '' }}"
                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        class="flex-1 rounded-full bg-paper-50 border border-paper-200 px-4 py-2 text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition">
                    <button type="button" id="wd-up-verify"
                        class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal shrink-0">{{ __('Verify') }}</button>
                </div>
                <p data-msg="0" class="mt-2 text-[12px] hidden"></p>
            </div>
        </section>

        {{-- Steps 1–5 --}}
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden" id="wd-up-steps" @if(!$verified) data-locked="1" @endif>
            <div class="px-5 py-4 border-b border-paper-200">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('update process') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-0.5">{{ __('Run each step in order') }}</h2>
            </div>
            <div class="p-5 space-y-3">
                @php
                    $steps = [
                        1 => [__('Backup files & database'), __('Full backup of code + database before any change.'), __('Start backup'), 'btn'],
                        2 => [__('Upload update ZIP'), __('Choose the update package (.zip). Its version is verified automatically.'), __('Choose ZIP'), 'file'],
                        3 => [__('Apply update'), __('Extracts and overwrites code only — images, .env and database stay safe.'), __('Apply'), 'btn'],
                        4 => [__('Run migrations'), __('Adds any new tables/columns. Never removes your data.'), __('Migrate'), 'btn'],
                        5 => [__('Finalize & health check'), __('Clears caches and verifies everything is healthy.'), __('Finalize'), 'primary'],
                    ];
                @endphp
                @foreach($steps as $n => $s)
                <div class="rounded-2xl border border-paper-200 p-4" data-step-card="{{ $n }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div data-badge="{{ $n }}" class="w-8 h-8 rounded-full bg-paper-100 text-ink-500 flex items-center justify-center text-[13px] font-bold shrink-0">{{ $n + 1 }}</div>
                            <div class="min-w-0">
                                <p class="font-medium text-ink-900 text-[13.5px]">{{ $s[0] }}</p>
                                <p class="text-[12px] text-ink-500">{{ $s[1] }}</p>
                            </div>
                        </div>
                        @if($s[3] === 'file')
                            <label data-step-btn="{{ $n }}"
                                class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium text-ink-900 cursor-pointer shrink-0">
                                <span data-btn-label="{{ $n }}">{{ $s[2] }}</span>
                                <input type="file" accept=".zip" class="hidden" id="wd-up-file">
                            </label>
                        @else
                            <button type="button" data-step-run="{{ $n }}" data-step-btn="{{ $n }}"
                                class="px-4 py-2 rounded-full text-[12px] font-semibold shrink-0 {{ $s[3] === 'primary' ? 'bg-wa-deep text-paper-0 hover:bg-wa-teal' : 'border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-900' }}">
                                <span data-btn-label="{{ $n }}">{{ $s[2] }}</span>
                            </button>
                        @endif
                    </div>
                    <p data-msg="{{ $n }}" class="mt-2 text-[12px] hidden"></p>
                    @if($n === 5)
                        <div data-health class="mt-3 grid grid-cols-2 gap-2 text-[12px] hidden"></div>
                    @endif
                </div>
                @endforeach
            </div>
        </section>

        {{-- Rollback --}}
        @if(count($backups) > 0)
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('safety net') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-0.5">{{ __('Rollback') }}</h2>
            </div>
            <div class="p-5 space-y-2">
                <p class="text-[12.5px] text-ink-600">{{ __('Restore code + database from a previous backup if something went wrong.') }}</p>
                @foreach($backups as $backup)
                <div class="flex items-center justify-between rounded-2xl border border-paper-200 p-3">
                    <div>
                        <p class="text-[13px] font-medium text-ink-900">v{{ $backup['version'] ?? '?' }}</p>
                        <p class="text-[11.5px] text-ink-500">{{ \Carbon\Carbon::parse($backup['created_at'] ?? now())->format('d M Y, H:i') }}</p>
                    </div>
                    <button type="button" data-rollback="{{ $backup['path'] ?? '' }}" data-version="{{ $backup['version'] ?? '' }}"
                        class="px-4 py-2 rounded-full border border-red-200 text-red-600 text-[12px] font-semibold hover:bg-red-50">{{ __('Rollback') }}</button>
                </div>
                @endforeach
            </div>
        </section>
        @endif
    </main>

    <script>
        window.WD_UPDATER = {
            csrf: '{{ csrf_token() }}',
            verified: @json((bool) $verified),
            urls: {
                verify:   '{{ route('admin.update.verify') }}',
                backup:   '{{ route('admin.update.backup') }}',
                upload:   '{{ route('admin.update.upload') }}',
                apply:    '{{ route('admin.update.apply') }}',
                migrate:  '{{ route('admin.update.migrate') }}',
                finalize: '{{ route('admin.update.finalize') }}',
                rollback: '{{ route('admin.update.rollback') }}',
            },
        };
    </script>
    <script src="{{ asset('js/admin-updater.js') }}?v={{ $currentBuild }}"></script>
</x-layouts.admin>

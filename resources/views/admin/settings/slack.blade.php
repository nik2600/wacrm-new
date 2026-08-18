<x-layouts.admin :title="__('Slack settings')" admin-key="settings" page="admin-settings-slack">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Slack') }}</span>
        </div>
        <div class="relative flex-1 max-w-[520px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" /></svg>
            <input class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition" placeholder="{{ __('Search inside settings...') }}" />
            <kbd class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">{{ __('CMD K') }}</kbd>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">
        @if (session('success'))
            <div class="px-4 py-2.5 rounded-xl bg-wa-bubble border border-wa-green/30 text-[12.5px] text-wa-deep flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 8 3 3 5-6" /></svg>
                {{ session('success') }}
            </div>
        @endif
        @if (isset($errors) && $errors->any())
            <div class="px-4 py-2.5 rounded-xl bg-accent-coral/10 border border-accent-coral/30 text-[12.5px] text-accent-coral">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.settings.slack.update') }}" class="space-y-5">
            @csrf

            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Slack') }} <span class="italic" style="color:#4A154B">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __("Slack doesn't need any central app credentials — each workspace creates its own Slack app and pastes its Bot Token + Signing Secret. Your job here is just to flip the platform-level switch and (optionally) review usage. The slash command lets a teammate send a WhatsApp message right from Slack.") }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    {{-- Feature toggle --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('slack · platform toggle') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Enable Slack') }}</h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <input type="hidden" name="slack_enabled" value="0">
                                <input type="checkbox" name="slack_enabled" value="1" @checked($enabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 text-[12.5px] text-ink-700 leading-relaxed">
                            {{ __('When enabled, workspace owners see the') }} <span class="font-mono text-wa-deep">{{ __('Connect now') }}</span> {{ __('button on the') }}
                            <a href="{{ url('/integrations') }}" class="text-wa-deep underline">{{ __('Integrations') }}</a> {{ __('page and can paste their Bot Token + Signing Secret at') }} <span class="font-mono text-wa-deep">/slack</span>.
                            {{ __('Each connection is workspace-scoped — the token + secret are encrypted at rest and only used to verify that workspace\'s Slack requests.') }}
                        </div>
                    </section>

                    {{-- Step-by-step guide --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('walkthrough you can share with customers') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('How a workspace connects Slack') }}</h2>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">
                                {{ __('Every workspace makes its own Slack app — six steps, ~5 minutes. We never see anything until they paste the two values at') }} <span class="font-mono text-wa-deep">/slack</span>.
                            </p>
                        </div>

                        <ol class="divide-y divide-paper-100">
                            @php $steps = [
                                ['Create a Slack app', 'Go to <span class="font-mono">api.slack.com/apps</span> → <span class="font-mono text-ink-900">Create New App</span> → <span class="font-mono text-ink-900">From scratch</span>. Name it and pick the Slack workspace.'],
                                ['Add bot scopes', 'Open <span class="font-mono">OAuth &amp; Permissions</span> → <span class="font-mono">Bot Token Scopes</span> → add <span class="font-mono text-ink-900">commands</span> and <span class="font-mono text-ink-900">chat:write</span> (add <span class="font-mono">users:read</span> only if you mention real Slack members).'],
                                ['Create the slash command', 'Open <span class="font-mono">Slash Commands</span> → <span class="font-mono text-ink-900">Create New Command</span>. Command <span class="font-mono text-ink-900">/wa</span>, and set the <strong>Request URL</strong> to the receiver shown in the sidebar.'],
                                ['Install the app', 'Click <span class="font-mono text-ink-900">Install to Workspace</span> → authorize. Copy the <span class="text-wa-deep font-semibold">Bot User OAuth Token</span> (starts with <span class="font-mono">xoxb-</span>).'],
                                ['Copy the Signing Secret', 'Open <span class="font-mono">Basic Information</span> → <span class="font-mono">App Credentials</span> → copy the <span class="text-wa-deep font-semibold">Signing Secret</span>. We use it to verify every request really came from Slack.'],
                                ['Paste at /slack', 'In '.brand_name().', open <a href="'.url('/slack').'" target="_blank" class="text-wa-deep underline">/slack</a> and paste the Bot Token + Signing Secret, then Connect.'],
                            ]; @endphp
                            @foreach ($steps as $i => $s)
                                <li class="px-5 py-4 flex items-start gap-4">
                                    <span class="w-7 h-7 rounded-full text-paper-0 grid place-items-center font-mono text-[12px] font-semibold shrink-0" style="background:#4A154B">{{ $i + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-[13px]">{{ __($s[0]) }}</div>
                                        <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">{!! $s[1] !!}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>

                    {{-- Usage --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('usage') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Live across workspaces') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">{{ __('Workspaces connected') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">{{ number_format($integrationsCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">{{ __('Active') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">{{ number_format($activeCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">{{ __('Commands handled') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">{{ number_format($logsCount) }}</div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Slash command Request URL') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Paste this into Slack') }}</h3>
                        </div>
                        <div class="p-4 space-y-2">
                            <p class="font-mono text-[10.5px] text-ink-700 break-all bg-paper-50 border border-paper-200 rounded-lg px-3 py-2">{{ $commandUrl }}</p>
                            <p class="text-[11px] text-ink-500">{{ __('Set this as the Request URL when creating the /wa slash command (step 3).') }}</p>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('How a teammate uses it') }}</div>
                        <p class="font-mono text-[11px] text-ink-700 mt-1.5 bg-paper-50 border border-paper-200 rounded-lg px-3 py-2">/wa send Rahul: your order is ready</p>
                        <p class="text-[11px] text-ink-500 mt-1.5">{{ __('Type the contact name as plain text — we match it to a WhatsApp contact and send.') }}</p>
                    </div>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Signature verified') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Every request is checked with HMAC-SHA256 against the Signing Secret (5-minute replay window) before any message is sent.') }}</p>
                    </div>
                </aside>
            </section>
        </form>
    </main>

</x-layouts.admin>

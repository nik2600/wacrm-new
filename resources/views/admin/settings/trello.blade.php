<x-layouts.admin :title="__('Trello settings')" admin-key="settings" page="admin-settings-trello">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Trello') }}</span>
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

        <form method="POST" action="{{ route('admin.settings.trello.update') }}" class="space-y-5">
            @csrf

            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Trello') }} <span class="italic" style="color:#0079BF">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __("Trello doesn't need any central app credentials — each workspace generates its own API key + token and picks a board. Your job here is just to flip the platform-level switch and (optionally) review usage. When a card is assigned or changes, the right person gets a WhatsApp message.") }}
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
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('trello · platform toggle') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Enable Trello') }}</h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <input type="hidden" name="trello_enabled" value="0">
                                <input type="checkbox" name="trello_enabled" value="1" @checked($enabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 text-[12.5px] text-ink-700 leading-relaxed">
                            {{ __('When enabled, workspace owners see the') }} <span class="font-mono text-wa-deep">{{ __('Connect now') }}</span> {{ __('button on the') }}
                            <a href="{{ url('/integrations') }}" class="text-wa-deep underline">{{ __('Integrations') }}</a> {{ __('page and can paste their API key, OAuth secret, token and board at') }} <span class="font-mono text-wa-deep">/trello</span>.
                            {{ __('Each connection is workspace-scoped — the secret + token are encrypted at rest, and we register a Trello webhook on their board automatically.') }}
                        </div>
                    </section>

                    {{-- Step-by-step guide --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('walkthrough you can share with customers') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('How a workspace connects Trello') }}</h2>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">
                                {{ __('Every workspace generates its own Trello key + token — five steps. We never see anything until they paste the values at') }} <span class="font-mono text-wa-deep">/trello</span>.
                            </p>
                        </div>

                        <ol class="divide-y divide-paper-100">
                            @php $steps = [
                                ['Open the Power-Ups admin', 'Go to <span class="font-mono">trello.com/power-ups/admin</span> and create a Power-Up (a placeholder is fine — nothing needs to be published). This is just where Trello keeps your API key.'],
                                ['Generate the API key + secret', 'Open the Power-Up → <span class="font-mono text-ink-900">API Key</span> tab → <span class="font-mono text-ink-900">Generate a new API Key</span>. Copy the <span class="text-wa-deep font-semibold">API key</span> and the <span class="text-wa-deep font-semibold">OAuth secret</span> (the secret verifies incoming webhooks).'],
                                ['Authorize a token', 'Open <span class="font-mono">https://trello.com/1/authorize?expiration=never&amp;scope=read&amp;response_type=token&amp;name='.brand_name().'&amp;key=YOUR_KEY</span> → Approve → copy the <span class="text-wa-deep font-semibold">token</span>. Use <span class="font-mono">expiration=never</span> so the webhook doesn\'t die.'],
                                ['Pick the board', 'Copy the <span class="text-wa-deep font-semibold">board ID</span> or just the <span class="font-mono">board URL</span> of the board to watch (e.g. <span class="font-mono">trello.com/b/AbCdEfGh/my-board</span>).'],
                                ['Paste at /trello', 'In '.brand_name().', open <a href="'.url('/trello').'" target="_blank" class="text-wa-deep underline">/trello</a> and paste API key + secret + token + board, then Connect. We register the webhook on the board automatically.'],
                            ]; @endphp
                            @foreach ($steps as $i => $s)
                                <li class="px-5 py-4 flex items-start gap-4">
                                    <span class="w-7 h-7 rounded-full text-paper-0 grid place-items-center font-mono text-[12px] font-semibold shrink-0" style="background:#0079BF">{{ $i + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-[13px]">{{ __($s[0]) }}</div>
                                        <p class="text-[12px] text-ink-600 mt-1 leading-relaxed break-words">{!! $s[1] !!}</p>
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
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">{{ __('Boards connected') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">{{ number_format($integrationsCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">{{ __('Active') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">{{ number_format($activeCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">{{ __('Events handled') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">{{ number_format($logsCount) }}</div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Webhook callback URL') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Trello posts here') }}</h3>
                        </div>
                        <div class="p-4 space-y-2">
                            <p class="font-mono text-[10.5px] text-ink-700 break-all bg-paper-50 border border-paper-200 rounded-lg px-3 py-2">{{ $callbackUrl }}</p>
                            <p class="text-[11px] text-ink-500">{{ __('Must be reachable over public HTTPS — Trello does a HEAD check before creating the webhook. We register it for the workspace automatically on connect.') }}</p>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('What triggers a message') }}</div>
                        <ul class="text-[11.5px] text-ink-700 mt-1.5 space-y-1">
                            <li><span class="font-mono text-wa-deep">addMemberToCard</span> — {{ __('assignee notified') }}</li>
                            <li><span class="font-mono text-wa-deep">createCard</span> — {{ __('card added') }}</li>
                            <li><span class="font-mono text-wa-deep">updateCard</span> — {{ __('moved / due / archived') }}</li>
                            <li><span class="font-mono text-wa-deep">deleteCard</span> — {{ __('card deleted') }}</li>
                        </ul>
                        <p class="text-[11px] text-ink-500 mt-1.5">{{ __('Assignment always notifies the assignee; add/update/delete can go to a fixed number set on the board.') }}</p>
                    </div>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Signature verified') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Every delivery is checked with the X-Trello-Webhook HMAC-SHA1 header against the OAuth secret before any message is sent.') }}</p>
                    </div>
                </aside>
            </section>
        </form>
    </main>

</x-layouts.admin>

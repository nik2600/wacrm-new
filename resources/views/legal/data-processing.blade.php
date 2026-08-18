<x-layouts.user :title="__('Data processing')" nav-key="more" page="legal-dpa">
    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-7 py-7 space-y-6">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Legal · GDPR Article 28') }}</div>
            <h1 class="font-serif text-[30px] sm:text-[36px] lg:text-[44px] leading-none">{{ __('Data processing agreement') }}</h1>
            <p class="text-[13px] text-ink-600 mt-2">
                {{ __('This page documents every external service :app auto-reply translation routes customer message data through, what it receives, and how to restrict cross-border transfer.', ['app' => brand_name()]) }}
            </p>
        </div>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
            <h2 class="font-serif text-[22px] mb-2">{{ __('What data leaves your server') }}</h2>
            <p class="text-[12.5px] text-ink-700 mb-4">
                {{ __('When an inbound WhatsApp message triggers a multilingual auto-reply, :app may send the', ['app' => brand_name()]) }}
                <strong>{{ __('message body') }}</strong> and the <strong>{{ __('reply text') }}</strong> to one or more
                of the following translation providers — depending on which drivers your administrator has activated at
                <a href="{{ url('/admin/translation-providers') }}"
                    class="text-wa-deep font-semibold hover:underline">/admin/translation-providers</a>.</p>

            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="bg-paper-50 border-y border-paper-200 text-ink-500">
                    <tr>
                        <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2">
                            {{ __('Provider') }}</th>
                        <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2">
                            {{ __('Region') }}</th>
                        <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2">
                            {{ __('Data sent') }}</th>
                        <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2">
                            {{ __('Provider DPA') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr>
                        <td class="px-3 py-2.5 font-medium">{{ __('MyMemory') }}</td>
                        <td class="px-3 py-2.5">{{ __('EU (Italy)') }}</td>
                        <td class="px-3 py-2.5">{{ __('Message text + reply text') }}</td>
                        <td class="px-3 py-2.5"><a href="https://translatedlabs.com/legal" target="_blank"
                                rel="noopener"
                                class="text-wa-deep font-semibold hover:underline">{{ __('translatedlabs.com/legal') }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2.5 font-medium">{{ __('Google (free / unofficial)') }}</td>
                        <td class="px-3 py-2.5">{{ __('Global') }}</td>
                        <td class="px-3 py-2.5">{{ __('Message text + reply text') }}</td>
                        <td class="px-3 py-2.5"><span
                                class="text-accent-coral">{{ __('No official DPA — unofficial endpoint') }}</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2.5 font-medium">{{ __('DeepL') }}</td>
                        <td class="px-3 py-2.5">{{ __('EU (Germany)') }}</td>
                        <td class="px-3 py-2.5">{{ __('Message text + reply text') }}</td>
                        <td class="px-3 py-2.5"><a href="https://www.deepl.com/en/pro-data-security" target="_blank"
                                rel="noopener"
                                class="text-wa-deep font-semibold hover:underline">{{ __('deepl.com/pro-data-security') }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2.5 font-medium">{{ __('Google Cloud Translation') }}</td>
                        <td class="px-3 py-2.5">{{ __('Global') }}</td>
                        <td class="px-3 py-2.5">{{ __('Message text + reply text') }}</td>
                        <td class="px-3 py-2.5"><a href="https://cloud.google.com/terms/data-processing-addendum"
                                target="_blank" rel="noopener"
                                class="text-wa-deep font-semibold hover:underline">{{ __('cloud.google.com/terms/dpa') }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2.5 font-medium">{{ __('LibreTranslate') }}</td>
                        <td class="px-3 py-2.5">{{ __('Your server') }}</td>
                        <td class="px-3 py-2.5">{{ __('Stays on-premise') }}</td>
                        <td class="px-3 py-2.5"><span
                                class="text-wa-deep">{{ __('Self-hosted — no third party') }}</span></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
            <h2 class="font-serif text-[22px] mb-2">{{ __('Restrict cross-border data transfer') }}</h2>
            <p class="text-[12.5px] text-ink-700 mb-4">
                {{ __('Set a stricter routing mode for this workspace. Auto-reply translation will only route through providers compatible with the selected mode — the rest are silently skipped at runtime.') }}
            </p>

            @php
                $ws = auth()->user()?->currentWorkspace;
                $current = $ws?->data_residency ?: 'any';
                $isOwner = $ws && (int) $ws->owner_user_id === (int) auth()->id();
            @endphp

            @if (!$isOwner)
                <p class="text-[12px] italic text-ink-500">
                    {{ __('Only the workspace owner can change this setting.') }}</p>
            @else
                <form method="POST" action="{{ route('user.settings.residency') }}" class="space-y-3">
                    @csrf
                    <label
                        class="block border border-paper-200 rounded-xl p-4 cursor-pointer {{ $current === 'any' ? 'border-wa-deep bg-wa-mint/30' : '' }}">
                        <input type="radio" name="data_residency" value="any" @checked($current === 'any')
                            class="mr-2" />
                        <span class="font-semibold text-[13px]">{{ __('No restriction') }}</span>
                        <p class="text-[11.5px] text-ink-500 mt-1 ml-5">
                            {{ __('Use whichever active provider the fallback chain picks. Best uptime, no compliance restrictions.') }}
                        </p>
                    </label>
                    <label
                        class="block border border-paper-200 rounded-xl p-4 cursor-pointer {{ $current === 'eu_only' ? 'border-wa-deep bg-wa-mint/30' : '' }}">
                        <input type="radio" name="data_residency" value="eu_only" @checked($current === 'eu_only')
                            class="mr-2" />
                        <span class="font-semibold text-[13px]">{{ __('EU-only routing') }}</span>
                        <p class="text-[11.5px] text-ink-500 mt-1 ml-5">
                            {{ __('Only DeepL (Germany) and self-hosted LibreTranslate are allowed. Compatible with GDPR Article 44 cross-border transfer rules.') }}
                        </p>
                    </label>
                    <label
                        class="block border border-paper-200 rounded-xl p-4 cursor-pointer {{ $current === 'local' ? 'border-wa-deep bg-wa-mint/30' : '' }}">
                        <input type="radio" name="data_residency" value="local" @checked($current === 'local')
                            class="mr-2" />
                        <span class="font-semibold text-[13px]">{{ __('On-premise only') }}</span>
                        <p class="text-[11.5px] text-ink-500 mt-1 ml-5">
                            {{ __('Only the self-hosted LibreTranslate driver is allowed. Customer message text never leaves your own server. Requires LibreTranslate to be configured.') }}
                        </p>
                    </label>
                    <div class="flex justify-end pt-2">
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save residency setting') }}</button>
                    </div>
                </form>
            @endif
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
            <h2 class="font-serif text-[22px] mb-2">{{ __('Retention & cache') }}</h2>
            <p class="text-[12.5px] text-ink-700">{{ __('Translated phrases are cached for') }} <strong>24
                    hours</strong> on this
                {{ brand_name() }} install. After that the
                cache entry expires and the next request re-routes through the active provider. Translation API
                providers themselves may retain logs per their own DPAs — see the linked policies above.</p>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
            <h2 class="font-serif text-[22px] mb-2">{{ __('Right to erasure') }}</h2>
            <p class="text-[12.5px] text-ink-700">
                {{ __(':app stores translated reply variants alongside each auto-reply rule in your database (column', ['app' => brand_name()]) }}
                <code
                    class="bg-paper-50 px-1.5 py-0.5 rounded font-mono text-[11px]">keyword_replies.keyword_translations</code>).
                Deleting a contact or workspace via the standard delete flows removes their conversation history; cached
                translations are not personally identifiable as they are keyed by phrase, not by contact.</p>
        </section>

    </main>
</x-layouts.user>

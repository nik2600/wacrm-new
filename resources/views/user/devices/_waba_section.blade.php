{{--
 /devices when active engine = waba.
 Renders a card grid of WaProviderConfig rows (one card per WABA
 phone number) instead of the Baileys device table.

 Vars expected (passed from DevicesController):
 $wabaAccounts — Collection of WaProviderConfig rows
 $embeddedSignupReady — bool, opens FB SDK iframe modal when true
 $embeddedSignupConfigId — string
 $wabaAppId — string
--}}
<section class="space-y-5">

    {{-- Empty state --}}
    @if ($wabaAccounts->isEmpty())
        @if (!empty($multiEngine))
            {{-- Multi-engine: NO big connect card on the page. Connect happens via
                 "Add device" → Meta (WABA) modal. Just a slim "not connected" line. --}}
            <div
                class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl px-5 py-4 text-[12.5px] text-ink-500">
                {{ __('No WhatsApp Business numbers connected yet. Click "Add device" above, then choose Meta (WABA).') }}
            </div>
        @else
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-10 text-center shadow-card">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-wa-bubble text-wa-deep mb-3">
                <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path
                        d="M3 5.5A2.5 2.5 0 0 1 5.5 3h13A2.5 2.5 0 0 1 21 5.5v4A2.5 2.5 0 0 1 18.5 12H13l-4 4v-4H5.5A2.5 2.5 0 0 1 3 9.5v-4Z" />
                </svg>
            </div>
            <div class="font-serif text-[24px] leading-tight">{{ __('Connect your first WABA number') }}</div>
            <p class="text-[12.5px] text-ink-600 mt-2 max-w-md mx-auto">
                {{ __('Each merchant connects their own WhatsApp Business Account from Meta Business Suite. You can add multiple numbers per workspace.') }}
            </p>
            <button data-waba-connect="{{ $embeddedSignupReady ? 'embedded' : 'manual' }}" type="button"
                class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">
                @if ($embeddedSignupReady)
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                        <circle cx="8" cy="8" r="7" />
                    </svg>
                    Continue with Facebook
                @else
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add WABA account
                @endif
            </button>
            @if ($embeddedSignupReady)
                <div class="mt-3 text-[11px] text-ink-500">
                    <button data-waba-connect="manual" type="button"
                        class="hover:underline">{{ __('or paste credentials manually →') }}</button>
                </div>
            @endif
        </div>
        @endif
    @else
        {{-- Filter + add bar --}}
        <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
            <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-2 text-[12px]">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Workspace · WABA accounts') }}</span>
                    <span class="font-mono text-[11px] text-ink-700">{{ $wabaAccounts->count() }}
                        {{ __('connected') }}</span>
                </div>
                <button data-waba-connect="{{ $embeddedSignupReady ? 'embedded' : 'manual' }}" type="button"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add WABA account
                </button>
            </div>

            {{-- Card grid (one per row) --}}
            <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-3">
                @foreach ($wabaAccounts as $waba)
                    @php
                        $meta = is_array($waba->meta_json) ? $waba->meta_json : [];
                        $wabaId = (string) ($meta['waba_id'] ?? '');
                        $pnid = (string) ($meta['phone_number_id'] ?? '');
                        $bizId = (string) ($meta['business_id'] ?? '');
                        $verified =
                            (string) ($meta['verified_name'] ??
                                ($waba->display_label ?: ($waba->phone_number ?: 'Unnamed WABA')));
                        $quality = strtoupper((string) ($meta['quality_rating'] ?? ''));
                        $tier = (string) ($meta['messaging_limit_tier'] ?? '');
                        // Coexistence: the number is still live on the WhatsApp
                        // Business app (onboarded in coexistence mode, /register
                        // skipped). Badge it so operators can tell at a glance.
                        $isCoexistence = (bool) ($meta['coexistence'] ?? false);
                        $isConnected = $waba->isConnected();
                        $qualityColor = match ($quality) {
                            'GREEN' => 'bg-wa-mint text-wa-deep border-wa-green/40',
                            'YELLOW' => 'bg-accent-amber/10 text-accent-amber border-accent-amber/40',
                            'RED' => 'bg-accent-coral/10 text-accent-coral border-accent-coral/40',
                            default => 'bg-paper-100 text-ink-700 border-paper-200',
                        };
                    @endphp
                    <div
                        class="border {{ $waba->is_primary ? 'border-wa-deep/40 ring-1 ring-wa-deep/20' : 'border-paper-200' }} rounded-2xl bg-paper-0 p-5 shadow-card relative">
                        @if ($waba->is_primary)
                            <span
                                class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 text-[10px] font-mono uppercase tracking-wide">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span> Primary
                            </span>
                        @endif

                        <div class="flex items-start gap-3">
                            <div
                                class="w-11 h-11 rounded-2xl bg-wa-bubble text-wa-deep grid place-items-center shrink-0">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor">
                                    <path
                                        d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-serif text-[18px] leading-tight truncate">{{ $verified }}</div>
                                <div class="font-mono text-[11.5px] text-ink-500 mt-0.5">
                                    {{ $waba->phone_number ?: '+— unknown' }}</div>
                                @if ($isCoexistence)
                                    <span
                                        class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep border border-wa-green/40 text-[10px] font-mono uppercase tracking-wide">
                                        <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span> {{ __('Coexistence') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-2 text-[11.5px]">
                            <dt class="text-ink-500 font-mono uppercase tracking-wide text-[10px]">{{ __('WABA ID') }}
                            </dt>
                            <dd class="text-right font-mono text-ink-700 truncate min-w-0">{{ $wabaId ?: '—' }}</dd>
                            <dt class="text-ink-500 font-mono uppercase tracking-wide text-[10px]">{{ __('Phone ID') }}
                            </dt>
                            <dd class="text-right font-mono text-ink-700 truncate min-w-0">{{ $pnid ?: '—' }}</dd>
                            <dt class="text-ink-500 font-mono uppercase tracking-wide text-[10px]">{{ __('Quality') }}
                            </dt>
                            <dd class="text-right">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ $qualityColor }}">
                                    {{ $quality ?: 'Unrated' }}
                                </span>
                            </dd>
                            <dt class="text-ink-500 font-mono uppercase tracking-wide text-[10px]">{{ __('Tier') }}
                            </dt>
                            <dd class="text-right font-mono text-ink-700">
                                {{ $tier ? str_replace('TIER_', '', $tier) . ' / day' : '—' }}</dd>
                        </dl>

                        <div
                            class="mt-4 pt-4 border-t border-paper-200 flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="inline-flex items-center gap-1 text-[11px] {{ $isConnected ? 'text-wa-deep' : 'text-accent-coral' }}">
                                    <span
                                        class="w-1.5 h-1.5 rounded-full {{ $isConnected ? 'bg-wa-green' : 'bg-accent-coral' }}"></span>
                                    {{ $isConnected ? 'Connected' : 'Disconnected' }}
                                </span>
                                @if ($waba->connected_at)
                                    <span class="text-[10.5px] text-ink-500 font-mono">·
                                        {{ $waba->connected_at->diffForHumans() }}</span>
                                @endif
                                @if ($isConnected)
                                    @include('user.devices._inbound_badge', ['wired' => (is_array($waba->meta_json ?? null) ? ($waba->meta_json['inbound_wired'] ?? null) : null)])
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                @if (!$waba->is_primary && $isConnected)
                                    <form method="POST" action="{{ url('/devices/waba/' . $waba->id . '/primary') }}"
                                        class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11px] font-semibold text-ink-700">
                                            {{ __('Set primary') }}
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('user.devices.waba.health', $waba->id) }}"
                                    class="px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11px] font-semibold text-ink-700 inline-flex items-center gap-1"
                                    title="{{ __('Account health — live Meta diagnostics') }}">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1.5 8h3l1.5-4 3 9 1.5-5H14.5" />
                                    </svg>
                                    {{ __('Health') }}
                                </a>
                                @if ($isConnected)
                                    {{-- Re-point this number's Meta webhook (override_callback_uri)
                                         at our current inbound URL, reusing the stored token. One-click
                                         fix for inbound going dead after a domain change. --}}
                                    <form method="POST" action="{{ url('/devices/waba/' . $waba->id . '/resubscribe') }}"
                                        class="inline"
                                        data-confirm="Re-subscribe {{ $verified }} to inbound webhooks? Use this if incoming messages aren't arriving. No re-login needed.">
                                        @csrf
                                        <button type="submit"
                                            class="px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11px] font-semibold text-ink-700 inline-flex items-center gap-1"
                                            title="{{ __('Re-subscribe inbound webhook — fixes incoming messages not arriving') }}">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                                stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13.5 3.5v3h-3M2.5 12.5v-3h3" />
                                                <path d="M12.4 6a4.5 4.5 0 0 0-8.2-.8M3.6 10a4.5 4.5 0 0 0 8.2.8" />
                                            </svg>
                                            {{ __('Fix inbound') }}
                                        </button>
                                    </form>
                                @endif
                                {{-- Register on WhatsApp Cloud API — completes registration so
                                     Meta stops rejecting sends with (#133010) "Account not
                                     registered". Shown regardless of CONNECT state (a PENDING pure
                                     Cloud-API number needs exactly this), but ONLY for
                                     non-coexistence numbers: Meta rejects /register for SMB /
                                     coexistence numbers with "(#100) Register endpoint is not
                                     available for SMB businesses", so the button would only ever
                                     fail there (they don't use Cloud-API registration anyway). --}}
                                @unless ($isCoexistence)
                                    <form method="POST" action="{{ url('/devices/waba/' . $waba->id . '/register') }}"
                                        class="inline"
                                        data-prompt-reason="Registers {{ $verified }} on the WhatsApp Cloud API so Meta stops rejecting sends (#133010 “Account not registered”). If this number has a two-step-verification PIN, enter it below — otherwise leave blank."
                                        data-prompt-title="Register on Cloud API"
                                        data-prompt-placeholder="Two-step PIN (optional)"
                                        data-prompt-field="pin"
                                        data-confirm-text="Register">
                                        @csrf
                                        <input type="hidden" name="pin">
                                        <button type="submit"
                                            class="px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11px] font-semibold text-ink-700 inline-flex items-center gap-1"
                                            title="{{ __('Register on Cloud API — fixes “Account not registered” (133010)') }}">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                                stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3.5 8.5l3 3 6-7" />
                                            </svg>
                                            {{ __('Register') }}
                                        </button>
                                    </form>
                                @endunless
                                @if ($wabaId)
                                    <a href="https://business.facebook.com/wa/manage/phone-numbers/?waba_id={{ $wabaId }}"
                                        target="_blank" rel="noopener"
                                        class="px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11px] font-semibold text-ink-700 inline-flex items-center gap-1"
                                        title="{{ __('Open in Meta Business Suite') }}">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <path d="M6 3H3v10h10v-3M9 3h4v4M13 3l-7 7" />
                                        </svg>
                                        Meta
                                    </a>
                                @endif
                                @if ($isConnected)
                                    <form method="POST" action="{{ url('/devices/waba/' . $waba->id . '/disconnect') }}"
                                        class="inline"
                                        data-confirm="Disconnect {{ $verified }}? This stops sends from this number; you'll have to re-authorize to use it again.">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="px-2.5 py-1 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[11px] font-semibold">
                                            {{ __('Disconnect') }}
                                        </button>
                                    </form>
                                @endif
                                {{-- Remove = permanently delete the number from this workspace's
                                     device list (wabaRemove deletes the wa_provider_configs row). --}}
                                <form method="POST" action="{{ url('/devices/waba/' . $waba->id . '/remove') }}"
                                    class="inline"
                                    data-confirm="Remove {{ $verified }} from this workspace? This permanently deletes it from your device list — you can re-add it later with Add WABA account.">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="px-2.5 py-1 rounded-full border border-accent-coral/60 bg-accent-coral/5 text-accent-coral hover:bg-accent-coral/15 text-[11px] font-semibold">
                                        {{ __('Remove') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between gap-2 flex-wrap text-[11px] text-ink-500">
                <div>{{ $wabaAccounts->count() }} WABA
                    {{ \Illuminate\Support\Str::plural('account', $wabaAccounts->count()) }} {{ __('connected') }}
                </div>
                <div class="font-mono">{{ __('Primary number is used as the default sender') }}</div>
            </div>
        </div>
    @endif

    {{-- Connect modals (hidden until [data-waba-connect="..."] click) --}}
    @include('user.devices._waba_modals', [
        'embeddedSignupReady' => $embeddedSignupReady ?? false,
        'embeddedSignupConfigId' => $embeddedSignupConfigId ?? '',
        'wabaAppId' => $wabaAppId ?? '',
    ])

</section>

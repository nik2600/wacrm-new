<x-layouts.user :title="__('WhatsApp Pay')" nav-key="connect" page="user-store-payments-index">
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'payments', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 min-w-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">{{ __('Store / Payments') }}</div>
                    <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">{{ __('WhatsApp Pay') }} <span class="italic text-wa-deep">{{ __('in-chat') }}</span></h1>
                    <p class="text-[13px] text-ink-600 mt-1 max-w-2xl">
                        {{ __('Let customers pay for their order WITHOUT leaving WhatsApp — they tap “Review and Pay” right in the chat (UPI / cards / netbanking / wallets via your gateway). You collect the money in your own Razorpay / PayU / BillDesk / Zaakpay account.') }}</p>
                </div>

                {{-- India-only banner --}}
                <div class="rounded-[14px] border {{ $regionOk ? 'border-wa-green/30 bg-wa-mint/50' : 'border-amber-300 bg-amber-50' }} px-4 py-3 text-[12.5px] flex items-start gap-2.5">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 shrink-0 {{ $regionOk ? 'text-wa-deep' : 'text-amber-600' }}" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6.5"/><path d="M8 5v3.5M8 11h.01"/></svg>
                    <div class="text-ink-700">
                        <b>{{ __('India only.') }}</b>
                        {{ __('Native in-chat WhatsApp Pay is currently live only for businesses in India (Meta restriction). Your WABA number was detected as') }}
                        <span class="font-mono">{{ $country ?: __('unknown') }}</span>.
                        @unless ($regionOk)
                            {{ __('Outside India, use a payment link instead (Store → Orders → “Send payment link”).') }}
                        @endunless
                    </div>
                </div>

                @if (session('status'))
                    <div class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">{{ session('status') }}</div>
                @endif
                @error('wapay')
                    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-2 text-[12.5px] text-red-700 font-mono">{{ $message }}</div>
                @enderror
                @error('config_name')
                    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-2 text-[12.5px] text-red-700 font-mono">{{ $message }}</div>
                @enderror

                {{-- KPIs --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Configs') }}</div>
                        <div class="font-serif text-[28px] leading-tight mt-1">{{ number_format($configs->count()) }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Active') }}</div>
                        <div class="font-serif text-[28px] leading-tight mt-1">{{ number_format($configs->where('is_active', true)->count()) }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('WABA') }}</div>
                        <div class="font-serif text-[18px] leading-tight mt-1.5 truncate">{{ $waba ? ($waba->phone_number ?: $waba->display_label) : __('Not connected') }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {{-- Add / update configuration --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                        <h2 class="font-serif text-[19px] leading-tight">{{ __('Add a payment configuration') }}</h2>
                        <p class="text-[12px] text-ink-500 mt-1">{{ __('Paste the Direct-Pay-Method name you created in WhatsApp Manager and pick its gateway.') }}</p>
                        <form method="POST" action="{{ route('user.store.payments.store') }}" class="mt-4 space-y-3.5">
                            @csrf
                            <div>
                                <label class="block text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1">{{ __('Configuration name') }}</label>
                                <input name="config_name" required maxlength="60" value="{{ old('config_name') }}" placeholder="e.g. my-razorpay-pay"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-50 text-[13px] focus:outline-none focus:border-wa-deep">
                                <p class="text-[11px] text-ink-500 mt-1">{{ __('Must EXACTLY match the name in') }} <span class="font-mono">WhatsApp Manager → Payment configurations → India</span>.</p>
                            </div>
                            <div>
                                <label class="block text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1">{{ __('Gateway') }}</label>
                                <select name="payment_type" class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-50 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @foreach (\App\Models\WorkspacePaymentConfig::PAYMENT_TYPES as $pt)
                                        <option value="{{ $pt }}" @selected(old('payment_type') === $pt)>{{ ucfirst($pt) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-[12.5px] text-ink-700">
                                <input type="checkbox" name="is_active" value="1" checked class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                                {{ __('Active') }}
                            </label>
                            <label class="flex items-start gap-2 text-[12.5px] text-ink-700">
                                <input type="checkbox" name="auto_charge" value="1" @checked(optional($configs->firstWhere('is_active', true))->meta_json['auto_charge'] ?? false) class="mt-0.5 rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                                <span>{{ __('Auto-charge new orders') }} <span class="block text-[11px] text-ink-500">{{ __('Automatically send the in-chat pay request the moment a catalog/storefront order is placed.') }}</span></span>
                            </label>
                            <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-wa-deep text-white text-[13px] font-semibold hover:bg-wa-deep/90 transition">{{ __('Save configuration') }}</button>
                        </form>
                    </div>

                    {{-- How-to helper --}}
                    <div class="bg-wa-bubble/40 border border-wa-green/20 rounded-2xl p-5 text-[12.5px] text-ink-700 leading-relaxed">
                        <h2 class="font-serif text-[19px] leading-tight text-ink-900">{{ __('How to set it up') }}</h2>
                        <ol class="mt-3 space-y-2 list-decimal pl-4">
                            <li>{{ __('In') }} <span class="font-mono">WhatsApp Manager → Payments → India</span>, {{ __('create a “Direct Pay Method” and link your Razorpay / PayU / BillDesk / Zaakpay account.') }}</li>
                            <li>{{ __('Give it a name and copy that name here (it can’t be created from our app — it’s a Meta-side step).') }}</li>
                            <li>{{ __('Save above, then go to') }} <a href="{{ route('user.store.orders.index') }}" class="text-wa-deep font-semibold hover:underline">{{ __('Store → Orders') }}</a> {{ __('and use “Request payment on WhatsApp” on any order.') }}</li>
                            <li>{{ __('The customer taps Review and Pay → pays in-chat → the order is marked paid automatically (confirmed via Meta’s lookup, never trusting the webhook alone).') }}</li>
                        </ol>
                    </div>
                </div>

                {{-- Existing configs --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-paper-200 font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Your configurations') }}</div>
                    @forelse ($configs as $c)
                        <div class="flex items-center gap-3 px-5 py-3 border-b border-paper-100 last:border-0">
                            <span class="w-9 h-9 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1.5 4.5h13v7h-13z M1.5 7h13"/></svg></span>
                            <div class="min-w-0">
                                <div class="text-[13px] font-semibold truncate">{{ $c->config_name }}</div>
                                <div class="text-[11px] font-mono text-ink-500">{{ strtoupper($c->payment_type) }} · {{ $c->currency }} · {{ $c->country }}</div>
                            </div>
                            <span class="ml-auto inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono {{ $c->is_active ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-50 text-ink-500 border border-paper-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $c->is_active ? 'bg-wa-green' : 'bg-paper-300' }}"></span>{{ $c->is_active ? __('Active') : __('Off') }}</span>
                            <form method="POST" action="{{ route('user.store.payments.destroy', $c->id) }}" onsubmit="return confirm('{{ __('Remove this configuration?') }}')">
                                @csrf @method('DELETE')
                                <button class="text-[11px] text-accent-coral hover:underline px-2 py-1">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-[13px] text-ink-500">{{ __('No payment configurations yet. Add one above to start collecting in-chat payments.') }}</div>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</x-layouts.user>

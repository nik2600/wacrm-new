{{--
 /admin/settings/mail — SMTP credentials used by every Mailable in the
 codebase. Saved values are applied in AppServiceProvider::boot()
 via App\Support\MailConfig::apply() so they take effect immediately
 on every subsequent request — no .env edit required.
--}}
<x-layouts.admin :title="__('Mail settings')" admin-key="settings">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Mail') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Project settings') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Mail') }}
                    <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('SMTP host, port, encryption and credentials. Saved values are applied at boot — every welcome / verification / receipt / support mailable in the system uses these without restarting the app.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/settings') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                <button type="submit" form="mail-form"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
            </div>
        </div>

        @if (session('success'))
            <div
                class="rounded-xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-3 text-[12.5px] font-medium">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px] font-medium">
                {{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div
                class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <div class="font-semibold mb-1">{{ __('Please fix the highlighted fields:') }}</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
            <div class="space-y-5 min-w-0">

                {{-- SMTP credentials --}}
                <form id="mail-form" method="POST" action="{{ route('admin.settings.mail.update') }}">
                    @csrf @method('PATCH')
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Outbound mail') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('SMTP credentials') }}</h2>
                            </div>
                            <span
                                class="rounded-full bg-wa-bubble text-wa-deep border border-wa-green/40 px-2.5 py-1 text-[11px] font-mono">{{ __('used everywhere') }}</span>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Sender name') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="mail_from_name" value="{{ old('mail_from_name', $mail['from_name']) }}"
                                    required maxlength="120"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Shown to recipients as the "From" name.') }}</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('From address') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input type="email" name="mail_from_address"
                                    value="{{ old('mail_from_address', $mail['from_address']) }}" required
                                    maxlength="200" placeholder="hello@yourdomain.com"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Domain must have valid SPF + DKIM or mail lands in spam.') }}</span>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Mailer') }} <span
                                        class="text-accent-coral">*</span></span>
                                <select name="mail_mailer" required
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    @php $m = old('mail_mailer', $mail['mailer'] ?: 'smtp'); @endphp
                                    <option value="smtp" @selected($m === 'smtp')>{{ __('smtp') }}</option>
                                    <option value="sendmail" @selected($m === 'sendmail')>{{ __('sendmail') }}</option>
                                    <option value="log" @selected($m === 'log')>{{ __('log (dev)') }}</option>
                                    <option value="mailgun" @selected($m === 'mailgun')>{{ __('mailgun') }}</option>
                                    <option value="ses" @selected($m === 'ses')>{{ __('ses (Amazon)') }}
                                    </option>
                                    <option value="postmark" @selected($m === 'postmark')>{{ __('postmark') }}</option>
                                    <option value="resend" @selected($m === 'resend')>{{ __('resend') }}</option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Encryption') }}</span>
                                <select name="mail_encryption"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    @php $enc = old('mail_encryption', $mail['encryption'] ?: 'tls'); @endphp
                                    <option value="tls" @selected($enc === 'tls')>{{ __('tls (port 587)') }}
                                    </option>
                                    <option value="ssl" @selected($enc === 'ssl')>{{ __('ssl (port 465)') }}
                                    </option>
                                    <option value="starttls" @selected($enc === 'starttls')>{{ __('starttls') }}</option>
                                    <option value="" @selected($enc === '')>{{ __('none') }}</option>
                                </select>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Host') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="mail_host" value="{{ old('mail_host', $mail['host']) }}" required
                                    maxlength="200" placeholder="{{ __('smtp.mailgun.org') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Port') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input type="number" name="mail_port"
                                    value="{{ old('mail_port', $mail['port'] ?: '587') }}" required min="1"
                                    max="65535"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('587 for TLS · 465 for SSL · 25 (avoid).') }}</span>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Username') }}</span>
                                <input name="mail_username" value="{{ old('mail_username', $mail['username']) }}"
                                    maxlength="200" placeholder="postmaster@mg.example.com"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Password') }}</span>
                                <input type="password" name="mail_password" maxlength="500" value=""
                                    autocomplete="new-password"
                                    placeholder="{{ $mail['password_set'] ? __('•••••••••••• (saved — leave blank to keep)') : __('Enter SMTP password') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Encrypted at rest with the app key.') }}</span>
                            </label>
                        </div>
                    </section>
                </form>

                {{-- Send test --}}
                <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Verify') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Send a test email') }}</h2>
                        <p class="text-[11.5px] text-ink-500 mt-1">
                            {{ __('Sends one short message with the currently-saved SMTP credentials. Use this to verify host / port / login before relying on the rest of the system.') }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.mail.test') }}"
                        class="p-5 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="space-y-1.5 flex-1 min-w-[280px]">
                            <span class="text-[11.5px] font-semibold">{{ __('Send to') }}</span>
                            <input type="email" name="to" required value="{{ auth()->user()->email }}"
                                placeholder="you@yourdomain.com"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                        </label>
                        <button type="submit"
                            class="px-4 py-2.5 rounded-full border border-wa-deep text-wa-deep hover:bg-wa-bubble text-[12px] font-semibold inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M2 8l12-6-3 14-3-6-6-2z" />
                            </svg>
                            {{ __('Send test') }}
                        </button>
                    </form>
                </section>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-[88px]">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Quick guide') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Common provider hosts') }}</h3>
                    </div>
                    <div class="p-4 space-y-2.5 text-[12px] text-ink-700">
                        <div><span class="font-semibold">{{ __('Mailgun:') }}</span> <span
                                class="font-mono text-[11px]">{{ __('smtp.mailgun.org') }}</span> · 587 / tls</div>
                        <div><span class="font-semibold">{{ __('SendGrid:') }}</span> <span
                                class="font-mono text-[11px]">{{ __('smtp.sendgrid.net') }}</span> · 587 / tls</div>
                        <div><span class="font-semibold">{{ __('Amazon SES:') }}</span> <span
                                class="font-mono text-[11px]">{{ __('email-smtp.<region>.amazonaws.com') }}</span> ·
                            587 / tls</div>
                        <div><span class="font-semibold">{{ __('Postmark:') }}</span> <span
                                class="font-mono text-[11px]">{{ __('smtp.postmarkapp.com') }}</span> · 587 / tls
                        </div>
                        <div><span class="font-semibold">{{ __('Resend:') }}</span> <span
                                class="font-mono text-[11px]">{{ __('smtp.resend.com') }}</span> · 587 / tls</div>
                        <div><span class="font-semibold">{{ __('Gmail:') }}</span> <span
                                class="font-mono text-[11px]">{{ __('smtp.gmail.com') }}</span> · 587 / tls ·
                            {{ __('app password required') }}</div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Used by') }}</div>
                        <h3 class="font-serif text-[16px] leading-tight mt-0.5">{{ __('Mailables in the system') }}
                        </h3>
                    </div>
                    <div class="p-4 space-y-1.5 text-[11.5px] text-ink-700">
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Welcome + verification emails') }}</div>
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Password reset') }}</div>
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Invoices + order receipts') }}</div>
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Support ticket replies') }}</div>
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Security alerts (new device, 2FA, etc.)') }}</div>
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Workspace invites') }}</div>
                    </div>
                </div>

                <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                    <div class="font-semibold text-[12.5px]">{{ __('Tip') }}</div>
                    <p class="text-[11.5px] text-ink-600 mt-1">
                        {{ __('Always click "Send test" after editing. A single typo on host or port silently breaks every transactional email in the platform.') }}
                    </p>
                </div>
            </aside>
        </section>
    </main>

</x-layouts.admin>

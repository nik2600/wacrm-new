<x-layouts.guest :title="__('Set a new password')" page="auth-reset-password">
    @php $__brandName = (string) brand_name(); @endphp

    <div class="grid lg:grid-cols-[1fr_540px] h-screen overflow-hidden">

        <!-- LEFT: visual showcase (mirrors login page) -->
        <aside class="auth-art relative hidden lg:flex flex-col p-10 text-paper-0 overflow-hidden">
            <div class="blob bg-wa-green w-[300px] h-[300px] -top-12 -left-12"></div>
            <div class="blob bg-accent-amber w-[260px] h-[260px] bottom-12 right-12"></div>

            <div class="relative z-10 flex-1 flex flex-col justify-center w-full">
                <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-5 mb-4">
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 8h10v6H3z" />
                                <path d="M5 8V6a3 3 0 0 1 6 0v2" />
                            </svg>
                        </span>
                        <div>
                            <div class="text-[14px] font-semibold leading-tight">{{ __('Set a new password') }}</div>
                            <div class="text-[12px] text-paper-0/75 leading-snug mt-1">
                                {{ __("Choose something you'll remember. Minimum 8 characters.") }}</div>
                        </div>
                    </div>
                </div>

                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mb-3">
                    {{ __('Account recovery') }}</div>
                <h1 class="font-serif text-[42px] leading-[1.05] tracking-[-0.01em]">{{ __('Almost') }} <span
                        class="italic text-wa-green">{{ __('there') }}</span>.</h1>
                <p class="mt-3 text-[13px] text-paper-0/85 leading-relaxed">
                    {{ __("After saving, you'll be redirected to the sign-in page so you can use the new password.") }}
                </p>
            </div>

            <div class="relative z-10 text-[11px] text-paper-0/60 font-mono mt-6 text-right">2026 {{ $__brandName }} / {{ app_default_country_name() }}</div>
        </aside>

        <!-- RIGHT: form -->
        <main class="flex flex-col justify-center px-6 py-10 lg:px-14">
            <div class="w-full max-w-[400px] mx-auto">
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Reset password') }}</div>
                <h2 class="font-serif text-[36px] leading-tight tracking-[-0.01em]">{{ __('Pick a new') }} <span
                        class="italic text-wa-deep">{{ __('password') }}</span>.</h2>
                <p class="text-[13px] text-ink-600 mt-2">
                    {{ __('Use at least 8 characters. Mix letters and numbers for safety.') }}</p>

                @if ($errors->any())
                    <div
                        class="mt-5 mb-1 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                        @foreach ($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}" class="space-y-4 mt-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}" />

                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Email') }}</label>
                        <input name="email" type="email" required value="{{ old('email', $email) }}"
                            autocomplete="email" placeholder="you@company.com"
                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('New password') }}</label>
                        <div class="relative">
                            <input id="pw" name="password" type="password" required minlength="8"
                                autocomplete="new-password" placeholder="********"
                                class="w-full px-3 py-2.5 pr-10 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <button type="button" id="pw-toggle"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-900"
                                title="{{ __('Show password') }}">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                                    <circle cx="8" cy="8" r="2" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Confirm password') }}</label>
                        <div class="relative">
                            <input id="pw2" name="password_confirmation" type="password" required minlength="8"
                                autocomplete="new-password" placeholder="********"
                                class="w-full px-3 py-2.5 pr-10 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <button type="button" id="pw2-toggle"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-900"
                                title="{{ __('Show password') }}">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                                    <circle cx="8" cy="8" r="2" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2 mt-2">
                        <span>{{ __('Save new password') }}</span>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </button>

                    <p class="text-[12.5px] text-ink-600 text-center mt-2"><a href="{{ route('login') }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Back to sign in') }}</a></p>
                </form>

                <script>
                    (function() {
                        function pair(btnId, inpId) {
                            const btn = document.getElementById(btnId);
                            const inp = document.getElementById(inpId);
                            if (!btn || !inp || btn.__wired) return;
                            btn.__wired = true;
                            btn.addEventListener('click', (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                inp.type = inp.type === 'password' ? 'text' : 'password';
                                btn.setAttribute('title', inp.type === 'password' ? 'Show password' : 'Hide password');
                            });
                        }

                        function wire() {
                            pair('pw-toggle', 'pw');
                            pair('pw2-toggle', 'pw2');
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', wire);
                        } else {
                            wire();
                        }
                    })();
                </script>
            </div>
        </main>

    </div>

</x-layouts.guest>

<x-layouts.user :title="__('Developers / API')" nav-key="more" page="user-developers-index">

    @php
        $keys = $keys ?? collect();
        $rawKey = session('api_key_once');
        $baseUrl = rtrim(config('app.url'), '/') . '/api/v1';
        // One key per workspace — the live (non-revoked) key, if any.
        $activeKey = $keys->first(fn($k) => !$k->revoked_at);
        $docsUrl = url('/developers/docs');
    @endphp

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <!-- ===== LEFT RAIL ===== -->
            <aside class="space-y-3">
                <!-- Platform card -->
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center" style="background:#EDE7F6">
                        <svg viewBox="0 0 16 16" class="w-7 h-7 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M6 4L2.5 8 6 12M10 4l3.5 4L10 12" />
                        </svg>
                    </div>
                    <div class="font-serif text-[18px] leading-tight">{{ __('Developer API') }}</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">
                        {{ __('REST · /api/v1') }}</div>
                    @if ($activeKey)
                        <div
                            class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-wa-mint border border-wa-green/40 text-wa-deep">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Key active') }}
                        </div>
                    @else
                        <div
                            class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-paper-50 border border-paper-200 text-ink-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-paper-300"></span>{{ __('No key yet') }}
                        </div>
                    @endif
                </div>

                <!-- Quick reference -->
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Base URL') }}</div>
                    <div class="flex items-center gap-1.5">
                        <code id="base-url"
                            class="flex-1 min-w-0 truncate px-2.5 py-1.5 rounded-lg bg-paper-50 border border-paper-200 font-mono text-[11.5px] text-ink-900">{{ $baseUrl }}</code>
                        <button type="button" data-copy="base-url"
                            class="w-8 h-8 shrink-0 rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 grid place-items-center text-ink-600"
                            title="{{ __('Copy') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <rect x="5" y="5" width="8" height="9" rx="1.5" />
                                <path d="M3 11V3a1 1 0 0 1 1-1h6" />
                            </svg>
                        </button>
                    </div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-3 mb-1.5">
                        {{ __('Auth header') }}</div>
                    <code
                        class="block px-2.5 py-1.5 rounded-lg bg-ink-900 font-mono text-[11px] text-wa-mint overflow-x-auto whitespace-nowrap">Authorization: Bearer &lt;key&gt;</code>
                </div>

                <!-- Help card -->
                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>{{ __('Need help?') }}
                    </div>
                    {{ __('Full request &amp; response reference, with copy-able examples for every endpoint.') }}
                    <a href="{{ $docsUrl }}" class="text-wa-deep font-semibold underline">{{ __('Read the docs') }}</a>.
                </div>
            </aside>

            <!-- ===== MAIN ===== -->
            <section class="space-y-5">

                <!-- Title row -->
                <div class="flex items-end justify-between gap-4 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            <a href="{{ url('/more') }}" class="hover:text-wa-deep">{{ __('More') }}</a>
                            <span class="mx-1.5 text-ink-500/60">/</span>
                            <span>{{ __('Developers') }}</span>
                        </div>
                        <h1
                            class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">
                            {{ __('Developer') }} <span class="italic text-wa-deep">{{ __('API keys') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __('Generate a key to call the :brand REST API from your own apps, CRM or automation — send messages, manage contacts, templates, broadcasts and more. One key per workspace.', ['brand' => brand_name()]) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ url('/more') }}"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M10 4l-4 4 4 4" />
                            </svg>{{ __('Back') }}
                        </a>
                        <a href="{{ $docsUrl }}" target="_blank" rel="noopener"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M4 2.5h6l3 3V13a.5.5 0 0 1-.5.5h-8A.5.5 0 0 1 4 13V3a.5.5 0 0 1 .5-.5Z" />
                                <path d="M6.5 7.5h4M6.5 9.5h4" />
                            </svg>{{ __('Open docs') }}
                        </a>
                    </div>
                </div>

                @if (session('error'))
                    <div
                        class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2.5 text-[12.5px] text-[#A1431F] inline-flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v4M8 11v.01" />
                        </svg>{{ session('error') }}
                    </div>
                @endif

                {{-- One-time raw key — flashed once on mint, never recoverable again. --}}
                @if ($rawKey)
                    <section class="rounded-2xl border border-wa-green/50 bg-wa-mint/60 p-5 shadow-card">
                        <div class="flex items-start gap-3">
                            <span class="w-9 h-9 rounded-xl bg-wa-green/20 text-wa-deep grid place-items-center shrink-0">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <path d="M6.5 11.5l-2.5-2.5M3.5 8.5l3 3 6-7" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="font-serif text-[17px] leading-tight text-wa-deep">
                                    {{ __('Your new API key') }}</h2>
                                <p class="mt-1 text-[12.5px] text-ink-600 leading-snug">
                                    {{ __('Copy this now and store it safely. For your security we only keep a hash — it will never be shown again.') }}
                                </p>
                                <div class="mt-3 flex items-center gap-2 flex-wrap">
                                    <code id="api-key-raw"
                                        class="flex-1 min-w-0 truncate px-3 py-2 rounded-lg bg-paper-0 border border-wa-green/40 font-mono text-[12.5px] text-ink-900">{{ $rawKey }}</code>
                                    <button type="button" data-copy-key
                                        class="px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <rect x="5" y="5" width="8" height="9" rx="1.5" />
                                            <path d="M3 11V3a1 1 0 0 1 1-1h6" />
                                        </svg>{{ __('Copy') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                @endif

                @if ($activeKey)
                    {{-- The single active key. --}}
                    <section class="rounded-2xl border border-paper-200 bg-paper-0 shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                            <h2 class="font-serif text-[18px] leading-tight">{{ __('Your API key') }}</h2>
                            <span
                                class="inline-flex items-center gap-1.5 text-[10px] font-mono text-wa-deep bg-wa-mint border border-wa-green/40 px-2 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Active') }}
                            </span>
                        </div>
                        <div class="p-5 flex items-center gap-4 flex-wrap">
                            <span class="w-11 h-11 rounded-xl bg-paper-100 text-ink-600 grid place-items-center shrink-0">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="6" cy="10" r="2.5" />
                                    <path d="M8 9l5-5M11 4l1.5 1.5M9.5 5.5L11 7" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-ink-900 text-[14px]">
                                    {{ $activeKey->name ?: __('Untitled key') }}</div>
                                <div class="mt-1 flex items-center gap-3 flex-wrap text-[11.5px] text-ink-500 font-mono">
                                    <code class="text-ink-700">{{ $activeKey->prefix }}&hellip;</code>
                                    <span>{{ __('Created') }}
                                        {{ optional($activeKey->created_at)->format('M j, Y') }}</span>
                                    <span>{{ __('Last used') }}
                                        {{ $activeKey->last_used_at ? $activeKey->last_used_at->diffForHumans() : __('never') }}</span>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('user.developers.keys.destroy', $activeKey->id) }}"
                                data-confirm-form data-confirm-title="{{ __('Revoke this key?') }}"
                                data-confirm-message="{{ __('Any app or integration using this key will immediately stop working. This cannot be undone. You can create a new key afterwards.') }}"
                                data-confirm-accept="{{ __('Revoke') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="px-3.5 py-2 rounded-full border border-paper-200 bg-paper-0 hover:border-accent-coral hover:text-accent-coral text-[12px] font-medium inline-flex items-center gap-1.5">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="M3 4.5h10M6 4.5V3h4v1.5M5 4.5l.5 8h5l.5-8" />
                                    </svg>{{ __('Revoke') }}
                                </button>
                            </form>
                        </div>
                        <div
                            class="px-5 py-2.5 bg-paper-50 border-t border-paper-200 text-[11.5px] text-ink-500 inline-flex items-center gap-1.5 w-full">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M8 7v4M8 5v.01" />
                            </svg>{{ __('Only one key is allowed per workspace. Revoke this one to issue a new key.') }}
                        </div>
                    </section>
                @else
                    {{-- No active key → create form. --}}
                    <section class="rounded-2xl border border-paper-200 bg-paper-0 p-5 shadow-card">
                        <h2 class="font-serif text-[18px] leading-tight">{{ __('Create your key') }}</h2>
                        <p class="mt-1 text-[12.5px] text-ink-500 leading-snug">
                            {{ __('Give the key a name so you remember where it is used. The key is shown once on creation.') }}
                        </p>
                        <form method="POST" action="{{ route('user.developers.keys.store') }}"
                            class="mt-4 flex items-end gap-2 flex-wrap">
                            @csrf
                            <div class="flex-1 min-w-[220px]">
                                <label for="key-name"
                                    class="block font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5">{{ __('Key name') }}</label>
                                <input id="key-name" name="name" type="text" maxlength="120" required
                                    value="{{ old('name') }}" placeholder="{{ __('e.g. Production backend') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                @error('name')
                                    <p class="mt-1.5 text-[11.5px] text-accent-coral">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit"
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-1.5">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.7">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>{{ __('Create key') }}
                            </button>
                        </form>
                    </section>
                @endif

                <!-- Quickstart -->
                <section class="rounded-2xl border border-paper-200 bg-paper-0 p-5 shadow-card">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <h2 class="font-serif text-[18px] leading-tight">{{ __('Quickstart') }}</h2>
                        <a href="{{ $docsUrl }}" target="_blank" rel="noopener"
                            class="text-[12px] font-semibold text-wa-deep hover:underline inline-flex items-center gap-1.5">
                            {{ __('Full reference') }}
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                        </a>
                    </div>
                    <p class="mt-1 text-[12.5px] text-ink-500">
                        {{ __('Send your first message — replace the key and recipient:') }}</p>
                    <pre id="quickstart-curl"
                        class="mt-3 px-4 py-3 rounded-xl bg-ink-900 text-[12px] leading-relaxed text-[#E8F0EE] overflow-x-auto font-mono">curl -X POST '{{ $baseUrl }}/messages' \
  -H 'Authorization: Bearer YOUR_API_KEY' \
  -H 'Content-Type: application/json' \
  -d '{"to":"919812345678","type":"text","text":"Hello from {{ brand_name() }}"}'</pre>
                </section>
            </section>
        </div>
    </main>
</x-layouts.user>

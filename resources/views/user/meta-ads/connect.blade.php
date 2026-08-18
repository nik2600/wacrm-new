<x-layouts.user :title="__('Connect Meta Ads')" nav-key="metaads" page="user-meta-ads-connect">
    <div class="hairline-b border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-7 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/meta-ads') }}"
                    class="w-8 h-8 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to Meta Ads') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Meta Ads / Connection') }}</div>
                    <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] leading-tight truncate">
                        {{ __('Connect your') }} <span class="italic text-wa-deep">{{ __('Meta Ads account') }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($connected)
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 mono font-mono"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Connected') }}</span>
                @elseif ($adminFallback)
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ __('Using platform keys') }}</span>
                @else
                    <span
                        class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-accent-amber/10 text-[#7B5A14] mono font-mono">{{ __('Not connected') }}</span>
                @endif
                <button type="submit" form="metaConnectForm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 8l5 5 7-9" />
                    </svg>
                    {{ $connected ? __('Save & continue') : __('Connect & continue') }}
                </button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-7 py-6">
        <div class="max-w-3xl mx-auto space-y-4">

            @if (session('status'))
                <div
                    class="rounded-2xl border border-wa-green/40 bg-wa-bubble px-4 py-3 text-[12.5px] text-wa-deep flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M5.5 8.5l1.8 1.8L10.5 6.5" />
                    </svg>{{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div
                    class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12.5px] text-[#A1431F] flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 5v4M8 11h.01" />
                    </svg>{{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div
                    class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12px] text-[#A1431F]">
                    <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                    <ul class="list-disc pl-4 space-y-0.5">
                        @foreach ($errors->all() as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($adminFallback && !$connected)
                <div
                    class="rounded-2xl border border-paper-200 bg-paper-50 px-4 py-3 text-[12px] text-ink-700 flex items-start gap-2.5">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 shrink-0 text-wa-deep" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 7.5v3M8 5h.01" />
                    </svg>
                    <span>{{ __('A platform Meta Ads account is available as a fallback, so you can run ads without connecting your own. Add your keys below to bill ads to your own account instead.') }}</span>
                </div>
            @endif

            <form id="metaConnectForm" method="POST" action="{{ route('user.meta-ads.keys.save') }}">
                @csrf
                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card">
                    @include('user.meta-ads._keys-fields')
                </div>

                <div class="flex items-center justify-between gap-3 mt-4">
                    <div>
                        @if ($connected)
                            <button type="submit" form="metaDisconnectForm"
                                class="text-[12px] font-medium text-accent-coral hover:underline">{{ __('Remove keys') }}</button>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ url('/meta-ads') }}"
                            class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                        <button type="submit"
                            class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg>
                            {{ $connected ? __('Save & continue') : __('Connect & continue') }}
                        </button>
                    </div>
                </div>
            </form>

            @if ($connected)
                <form id="metaDisconnectForm" method="POST" action="{{ route('user.meta-ads.keys.destroy') }}"
                    class="hidden">@csrf @method('DELETE')</form>
            @endif
        </div>
    </section>

</x-layouts.user>

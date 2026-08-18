<x-layouts.user :title="__('SLA policies — Unified Inbox')" nav-key="team-inbox" page="user-team-inbox-sla">

    @php
        $activeCount = $policies->count();
        $hasDefault = $policies->firstWhere('is_default');
    @endphp

    @if (session('status'))
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-4">
            <div class="px-4 py-2.5 rounded-xl bg-wa-bubble border border-wa-green/30 text-[12.5px] text-wa-deep flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 8 3 3 5-6" /></svg>
                {{ session('status') }}
            </div>
        </div>
    @endif
    @if ($errors->any())
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-4">
            <div class="px-4 py-2.5 rounded-xl bg-accent-coral/10 border border-accent-coral/30 text-[12.5px] text-accent-coral flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 5v3M8 11v.01" /><circle cx="8" cy="8" r="6" /></svg>
                {{ $errors->first() }}
            </div>
        </div>
    @endif

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <!-- =============== SIDEBAR =============== -->
            <aside class="space-y-3">

                <!-- Info card -->
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center bg-wa-bubble">
                        <svg viewBox="0 0 24 24" class="w-7 h-7 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
                        </svg>
                    </div>
                    <div class="font-serif text-[18px] leading-tight">{{ __('SLA policies') }}</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">{{ __('Unified Inbox') }}</div>
                    <div class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono {{ $activeCount ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-50 border border-paper-200 text-ink-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $activeCount ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                        {{ $activeCount ? trans_choice('{1}:count active|[2,*]:count active', $activeCount, ['count' => $activeCount]) : __('Not configured') }}
                    </div>
                </div>

                <!-- How it works steps -->
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">{{ __('How it works') }}</div>
                    <ol class="px-1 space-y-0.5">
                        <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-wa-deep text-paper-0 shrink-0 mt-0.5">1</span>
                            {{ __('Customer messages — the response clock starts') }}
                        </li>
                        <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500 shrink-0 mt-0.5">2</span>
                            {{ __('Agent replies before the target') }}
                        </li>
                        <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500 shrink-0 mt-0.5">3</span>
                            {{ __('Miss it — the chat is flagged breached') }}
                        </li>
                        <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500 shrink-0 mt-0.5">4</span>
                            {{ __('Breaches show in the inbox & Team Performance') }}
                        </li>
                    </ol>

                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-3 pb-1.5">{{ __('Related') }}</div>
                    <a href="{{ url('/team-inbox') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] text-ink-700 hover:bg-paper-50">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4l-4 4 4 4" /></svg>
                        {{ __('Back to inbox') }}
                    </a>
                </div>

                <!-- Help card -->
                <div class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                        {{ __('Enter targets in minutes') }}
                    </div>
                    {{ __('60 = 1 hour · 240 = 4 hours · 1440 = 1 day. The default policy applies workspace-wide.') }}
                </div>
            </aside>

            <!-- =============== MAIN =============== -->
            <section class="space-y-5">

                <!-- Page header -->
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            <a href="{{ url('/team-inbox') }}" class="hover:text-wa-deep">{{ __('Unified Inbox') }}</a>
                            <span class="mx-1.5 text-ink-500/60">/</span>
                            <span>{{ __('SLA policies') }}</span>
                        </div>
                        <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">
                            {{ __('Response') }} <span class="italic text-wa-deep">{{ __('targets') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __('Set how fast your team should first respond to and resolve conversations. When a customer messages, the clock starts — reply in time or the chat is flagged breached.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ url('/team-inbox') }}" class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4l-4 4 4 4" /></svg>
                            {{ __('Back') }}
                        </a>
                    </div>
                </div>

                {{-- ===== Existing policies ===== --}}
                <div class="space-y-4">
                    @forelse ($policies as $p)
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                            {{-- Two sibling forms, buttons wired by the HTML5 `form=`
                                 attribute — a proper blade-only edit + delete with no JS. --}}
                            <form id="sla-edit-{{ $p->id }}" method="POST" action="{{ url('/team-inbox/sla/' . $p->id) }}">@csrf @method('PUT')</form>
                            <form id="sla-del-{{ $p->id }}" method="POST" action="{{ url('/team-inbox/sla/' . $p->id) }}"
                                data-confirm="{{ __('Delete this SLA policy?') }}">@csrf @method('DELETE')</form>

                            <div class="flex items-center gap-2 mb-4">
                                <input form="sla-edit-{{ $p->id }}" name="name" value="{{ $p->name }}" required maxlength="128"
                                    class="font-serif text-[20px] leading-tight bg-transparent border-b border-transparent hover:border-paper-200 focus:border-wa-deep focus:outline-none min-w-0 flex-1">
                                @if ($p->is_default)
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-mono uppercase tracking-[0.14em] bg-wa-mint text-wa-deep border border-wa-green/40">{{ __('Default') }}</span>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="block space-y-1.5">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('First-response target (minutes)') }}</span>
                                    <input form="sla-edit-{{ $p->id }}" type="number" name="first_response_minutes" min="1" max="100000"
                                        value="{{ $p->first_response_minutes }}" placeholder="{{ __('e.g. 60') }}"
                                        class="w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                </label>
                                <label class="block space-y-1.5">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Resolution target (minutes)') }}</span>
                                    <input form="sla-edit-{{ $p->id }}" type="number" name="resolution_minutes" min="1" max="1000000"
                                        value="{{ $p->resolution_minutes }}" placeholder="{{ __('e.g. 1440') }}"
                                        class="w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                </label>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-4 text-[12px]">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input form="sla-edit-{{ $p->id }}" type="checkbox" name="pause_when_waiting_on_customer" value="1"
                                        @checked($p->pause_when_waiting_on_customer) class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                                    {{ __('Pause clock while waiting on the customer') }}
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input form="sla-edit-{{ $p->id }}" type="checkbox" name="respect_business_hours" value="1"
                                        @checked($p->respect_business_hours) class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                                    {{ __('Only count business hours') }}
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input form="sla-edit-{{ $p->id }}" type="checkbox" name="is_default" value="1"
                                        @checked($p->is_default) class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                                    {{ __('Make default (applies workspace-wide)') }}
                                </label>
                            </div>

                            <div class="flex items-center justify-between gap-2 mt-5 pt-4 border-t border-paper-200">
                                <button type="submit" form="sla-del-{{ $p->id }}"
                                    class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] font-semibold text-accent-coral hover:bg-accent-coral/10">{{ __('Delete') }}</button>
                                <button type="submit" form="sla-edit-{{ $p->id }}"
                                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-bold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                            </div>
                        </div>
                    @empty
                        <div class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 text-center">
                            <div class="font-serif text-[18px] mb-1">{{ __('No SLA policies yet') }}</div>
                            <p class="text-[12.5px] text-ink-500">{{ __('Create your first one below to start tracking response times.') }}</p>
                        </div>
                    @endforelse
                </div>

                {{-- ===== Create new ===== --}}
                <form method="POST" action="{{ url('/team-inbox/sla') }}"
                    class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    @csrf
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('New policy') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-0.5 mb-4">{{ __('Add a policy') }}</h2>

                    <label class="block space-y-1.5 mb-4">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Policy name') }} <span class="text-accent-coral">*</span></span>
                        <input name="name" required maxlength="128" placeholder="{{ __('e.g. Support — standard') }}"
                            class="w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="block space-y-1.5">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('First-response target (minutes)') }}</span>
                            <input type="number" name="first_response_minutes" min="1" max="100000" placeholder="{{ __('e.g. 60') }}"
                                class="w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                        </label>
                        <label class="block space-y-1.5">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Resolution target (minutes)') }}</span>
                            <input type="number" name="resolution_minutes" min="1" max="1000000" placeholder="{{ __('e.g. 1440') }}"
                                class="w-full rounded-lg border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mt-4 text-[12px]">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="pause_when_waiting_on_customer" value="1" class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                            {{ __('Pause clock while waiting on the customer') }}
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="respect_business_hours" value="1" class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                            {{ __('Only count business hours') }}
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" @checked($policies->isEmpty()) class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                            {{ __('Make default (applies workspace-wide)') }}
                        </label>
                    </div>

                    <div class="flex justify-end mt-5 pt-4 border-t border-paper-200">
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-bold hover:bg-wa-teal inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M8 3v10M3 8h10" /></svg>
                            {{ __('Create policy') }}
                        </button>
                    </div>
                </form>

            </section>
        </div>
    </main>
</x-layouts.user>

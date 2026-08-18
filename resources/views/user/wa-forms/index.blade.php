<x-layouts.user :title="__('WhatsApp Forms')" nav-key="more" page="user-wa-forms-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        @if (session('success'))
            <div
                class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="mb-4 bg-accent-coral/10 border border-accent-coral/30 rounded-lg px-4 py-2 text-[12.5px] text-accent-coral font-mono">
                {{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <aside class="space-y-3">
                <x-side-tip>
                    Forms render inside the WhatsApp chat — customers fill them without leaving the conversation. Build
                    once, publish to Meta, fire from any flow node or send directly from the inbox composer.
                </x-side-tip>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Quick stats') }}</div>
                    <div class="w-full flex items-center justify-between px-3 py-2 text-[13px]">
                        <span>{{ __('All forms') }}</span><span
                            class="font-mono text-[11px] text-ink-500">{{ $stats['all'] }}</span></div>
                    <div class="w-full flex items-center justify-between px-3 py-2 text-[13px]"><span
                            class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-wa-green"></span>Published</span><span
                            class="font-mono text-[11px] text-ink-500">{{ $stats['published'] }}</span></div>
                    <div class="w-full flex items-center justify-between px-3 py-2 text-[13px]"><span
                            class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-paper-200"></span>Draft</span><span
                            class="font-mono text-[11px] text-ink-500">{{ $stats['draft'] }}</span></div>
                    <div class="w-full flex items-center justify-between px-3 py-2 text-[13px]">
                        <span>{{ __('Submissions') }}</span><span
                            class="font-mono text-[11px] text-ink-500">{{ $stats['submissions'] }}</span></div>
                </div>

                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>Pro tip
                    </div>
                    {{ __("Use multi-step forms when you need more than 8-10 fields — WhatsApp's screen is small, and steps keep the bubble compact.") }}
                </div>
            </aside>

            <section class="space-y-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }}</div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('WhatsApp') }}
                            <span class="italic text-wa-deep">{{ __('Forms') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            {{ __('Native interactive forms inside the WhatsApp chat — capture leads, run surveys, take bookings.') }}
                        </p>
                    </div>
                    <a href="{{ url('/wa-forms/create') }}"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M8 3v10M3 8h10" />
                        </svg>
                        New form
                    </a>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total') }}
                        </div>
                        <div class="mt-2 font-serif text-[30px] leading-none">{{ $stats['all'] }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Published') }}</div>
                        <div class="mt-2 font-serif text-[30px] leading-none text-wa-deep">{{ $stats['published'] }}
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Drafts') }}
                        </div>
                        <div class="mt-2 font-serif text-[30px] leading-none">{{ $stats['draft'] }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Submissions') }}</div>
                        <div class="mt-2 font-serif text-[30px] leading-none">{{ $stats['submissions'] }}</div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
                  <div class="overflow-x-auto">
                    <div
                        class="min-w-[720px] px-4 py-2.5 grid grid-cols-[1.6fr_120px_100px_120px_220px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        <div>{{ __('Form') }}</div>
                        <div>{{ __('Type') }}</div>
                        <div>{{ __('Fields') }}</div>
                        <div>{{ __('Submissions') }}</div>
                        <div class="text-right pr-2">{{ __('Actions') }}</div>
                    </div>
                    @forelse ($forms as $form)
                        <div
                            class="min-w-[720px] px-4 py-3 grid grid-cols-[1.6fr_120px_100px_120px_220px] items-center gap-3 border-b border-paper-200 hover:bg-paper-50 transition">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-mono text-[9.5px] uppercase tracking-[0.14em] {{ $form->status === 'published' ? 'bg-wa-mint text-wa-deep' : ($form->status === 'paused' ? 'bg-accent-amber/15 text-[#7B5A14]' : 'bg-paper-100 text-ink-500') }}">
                                        <span
                                            class="w-1.5 h-1.5 rounded-full {{ $form->status === 'published' ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                                        {{ $form->status }}
                                    </span>
                                    <a href="{{ route('user.wa-forms.edit', $form->id) }}"
                                        class="font-serif text-[15px] leading-tight text-ink-900 hover:text-wa-deep truncate">{{ $form->title }}</a>
                                </div>
                                <div class="font-mono text-[10.5px] text-ink-500 mt-0.5 truncate">
                                    {{ $form->slug }}{{ $form->meta_flow_id ? ' · meta_flow_id ' . $form->meta_flow_id : '' }}
                                </div>
                            </div>
                            <div class="font-mono text-[11px] text-ink-700">
                                {{ str_replace('_', ' ', $form->audience_type) }}</div>
                            <div class="font-mono text-[11px] text-ink-700">{{ $form->fieldsCount() }}</div>
                            <div class="font-mono text-[11px]">
                                <a href="{{ route('user.wa-forms.submissions', $form->id) }}"
                                    class="text-wa-deep hover:underline">{{ $form->submissions_count ?? 0 }}</a>
                            </div>
                            <div class="text-right pr-2 flex items-center justify-end gap-1.5">
                                @if ($form->status !== 'published')
                                    <form action="{{ route('user.wa-forms.publish', $form->id) }}" method="POST"
                                        class="inline">@csrf
                                        <button
                                            class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal text-[11px] font-semibold">{{ __('Publish') }}</button>
                                    </form>
                                @else
                                    <form action="{{ route('user.wa-forms.publish', $form->id) }}" method="POST"
                                        class="inline">@csrf
                                        <button
                                            class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px]">{{ __('Re-publish') }}</button>
                                    </form>
                                @endif
                                <a href="{{ route('user.wa-forms.edit', $form->id) }}"
                                    class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px]">Edit</a>
                                <form action="{{ route('user.wa-forms.duplicate', $form->id) }}" method="POST"
                                    class="inline">@csrf<button
                                        class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px]">{{ __('Dup') }}</button>
                                </form>
                                <form action="{{ route('user.wa-forms.destroy', $form->id) }}" method="POST"
                                    class="inline"
                                    onsubmit="return confirm('Delete this form? Active flow nodes that reference it will break.');">
                                    @csrf @method('DELETE')
                                    <button
                                        class="px-2.5 py-1 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[11px]">×</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <span
                                class="inline-flex w-12 h-12 rounded-2xl bg-wa-mint text-wa-deep items-center justify-center mb-3">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="3" y="2" width="10" height="12" rx="1.5" />
                                    <path d="M5 5h6M5 8h6M5 11h4" />
                                </svg>
                            </span>
                            <div class="font-serif text-[18px] leading-tight mb-1">{{ __('No forms yet') }}</div>
                            <p class="text-[12.5px] text-ink-500 max-w-[420px] mx-auto mb-4">
                                {{ __('Build your first interactive form — drag inputs, set the destination, publish to Meta, and send it from any flow.') }}
                            </p>
                            <a href="{{ url('/wa-forms/create') }}"
                                class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal inline-flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                                Build a form
                            </a>
                        </div>
                    @endforelse
                  </div>

                    <div
                        class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500">
                        <div>{{ __('Showing') }} <span class="font-mono text-ink-900">{{ $forms->count() }}</span> of
                            <span class="font-mono text-ink-900">{{ $forms->total() }}</span></div>
                        @if ($forms->hasPages())
                            <div>{{ $forms->links() }}</div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>

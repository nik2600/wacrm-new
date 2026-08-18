<x-layouts.admin :title="__('Contact inbox')" admin-key="contact-messages" page="admin-contact-messages">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Contact inbox') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-6 space-y-5">

        <section
            class="bg-paper-0 border border-paper-200 rounded-2xl px-5 py-4 shadow-card flex items-center justify-between gap-5 flex-wrap">
            <div class="min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-1.5">
                    {{ __('Public site') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] leading-[1.0]">{{ __('Contact') }}
                    <span class="italic text-wa-deep">{{ __('inbox') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">{{ $totalCount }} {{ __('total') }} · <span
                        class="text-accent-coral font-semibold">{{ $unreadCount }} {{ __('unread') }}</span></p>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('admin.contact-messages.read-all') }}">
                    @csrf
                    <button
                        class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold text-ink-700">{{ __('Mark all read') }}</button>
                </form>
            @endif
        </section>

        <x-admin.flash />

        {{-- Filters --}}
        <form method="GET" class="flex items-center flex-wrap gap-2">
            <input type="search" name="q" value="{{ $search }}"
                placeholder="{{ __('Search name, email, message...') }}"
                class="flex-1 min-w-[220px] rounded-full border border-paper-200 bg-paper-0 px-4 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep">
            @foreach (['' => __('All'), 'unread' => __('Unread'), 'sales' => __('Sales'), 'support' => __('Support'), 'partnership' => __('Partnership'), 'other' => __('Other')] as $val => $label)
                <a href="{{ url('/admin/contact-messages') }}?filter={{ $val }}"
                    class="px-3.5 py-2 rounded-full text-[12px] font-semibold transition {{ ($filter ?? '') === $val ? 'bg-wa-deep text-paper-0' : 'border border-paper-200 bg-paper-0 text-ink-600 hover:bg-paper-50' }}">{{ $label }}</a>
            @endforeach
        </form>

        <section class="space-y-2.5">
            @forelse ($messages as $m)
                <div
                    class="bg-paper-0 border {{ $m->is_read ? 'border-paper-200' : 'border-wa-green/40' }} rounded-2xl p-4 shadow-card">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                @unless ($m->is_read)
                                    <span class="w-2 h-2 rounded-full bg-wa-green shrink-0"></span>
                                @endunless
                                <span class="text-[14px] font-semibold text-ink-900">{{ $m->name }}</span>
                                <a href="mailto:{{ $m->email }}"
                                    class="text-[12.5px] text-wa-deep hover:underline">{{ $m->email }}</a>
                                @if ($m->topic)
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10.5px] font-semibold uppercase tracking-wide">{{ $m->topic }}</span>
                                @endif
                                @if ($m->company)
                                    <span class="text-[11.5px] text-ink-500">· {{ $m->company }}</span>
                                @endif
                            </div>
                            <p class="text-[13px] text-ink-700 mt-2 leading-relaxed whitespace-pre-line">
                                {{ $m->message }}</p>
                            <div class="text-[11px] font-mono text-ink-400 mt-2">
                                {{ $m->created_at->format('M j, Y · H:i') }} @if ($m->ip)
                                    · {{ $m->ip }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <a href="mailto:{{ $m->email }}?subject={{ rawurlencode('Re: your message') }}"
                                title="{{ __('Reply') }}"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-paper-200 hover:bg-paper-50 text-ink-600">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M6 7 2 4v8h12V4l-4 3" />
                                    <path d="M2 4h12" />
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('admin.contact-messages.read', $m->id) }}">
                                @csrf
                                <button title="{{ $m->is_read ? __('Mark unread') : __('Mark read') }}"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-paper-200 hover:bg-paper-50 text-ink-600">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="M3 8.5 6.5 12 13 4.5" />
                                    </svg>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.contact-messages.destroy', $m->id) }}"
                                onsubmit="return confirm('{{ __('Delete this message?') }}')">
                                @csrf @method('DELETE')
                                <button title="{{ __('Delete') }}"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-paper-200 hover:bg-accent-coral/10 hover:border-accent-coral/40 text-ink-500 hover:text-accent-coral">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="M3 4.5h10M6 4.5V3h4v1.5M5 4.5l.5 8h5l.5-8" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-12 text-center">
                    <div class="text-[14px] font-semibold text-ink-700">{{ __('No messages yet') }}</div>
                    <p class="text-[12.5px] text-ink-500 mt-1">
                        {{ __('Submissions from the public contact form will appear here.') }}</p>
                </div>
            @endforelse
        </section>

        @if ($messages->hasPages())
            <div>{{ $messages->links() }}</div>
        @endif

    </main>

</x-layouts.admin>

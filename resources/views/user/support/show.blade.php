<x-layouts.user :title="__('Ticket #' . $ticket->ticket_number)" nav-key="more" page="user-support-show">

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                <a href="{{ url('/support') }}" class="hover:text-ink-900">{{ __('Support') }}</a> ·
                #{{ $ticket->ticket_number }}
            </div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[26px] sm:text-[30px] lg:text-[36px] leading-tight break-words">{{ $ticket->subject }}</h1>
            <div class="text-[12.5px] text-ink-600 mt-2 flex items-center gap-3 flex-wrap">
                @php
                    $stCls =
                        [
                            'open' => 'bg-accent-amber/15 text-accent-amber',
                            'in_progress' => 'bg-wa-bubble text-wa-deep',
                            'pending' => 'bg-accent-coral/10 text-accent-coral',
                            'resolved' => 'bg-wa-mint text-wa-deep',
                            'closed' => 'bg-paper-100 text-ink-600',
                        ][$ticket->status] ?? '';
                @endphp
                <span
                    class="px-2 py-0.5 rounded-full {{ $stCls }} text-[11px] font-mono">{{ str_replace('_', ' ', $ticket->status) }}</span>
                <span class="text-ink-500">Opened {{ optional($ticket->created_at)->diffForHumans() }}</span>
                @if ($ticket->resolved_at)
                    <span class="text-wa-deep">Resolved
                        {{ \Carbon\Carbon::parse($ticket->resolved_at)->diffForHumans() }}</span>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        {{-- Conversation thread --}}
        <section class="space-y-3">
            <div class="rounded-2xl border border-paper-200 bg-paper-50 p-4">
                <div class="text-[10.5px] font-mono text-ink-500 mb-1">{{ __('You · initial') }}</div>
                <div class="whitespace-pre-wrap text-[13px]">{{ $ticket->message }}</div>
            </div>
            @foreach ($messages as $m)
                @php
                    $isAdmin = $m->author_role === 'admin';
                    $tone = $isAdmin ? 'bg-wa-bubble border-wa-green/30' : 'bg-paper-0 border-paper-200';
                @endphp
                <div class="rounded-2xl border {{ $tone }} p-4">
                    <div class="flex items-center justify-between text-[10.5px] font-mono text-ink-500 mb-1">
                        <span>{{ $isAdmin ? 'Support team' : 'You' }}</span>
                        <span>{{ wa_local($m->created_at)?->format('M j, Y · H:i') }}</span>
                    </div>
                    <div class="whitespace-pre-wrap text-[13px]">{{ $m->body }}</div>
                </div>
            @endforeach
        </section>

        {{-- Reply form --}}
        @if (!in_array($ticket->status, ['closed'], true))
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                <h2 class="font-serif text-[18px] mb-3">{{ __('Reply') }}</h2>
                <form method="POST" action="{{ route('user.support.reply', $ticket->id) }}" class="space-y-3">
                    @csrf
                    <textarea name="body" rows="4" required
                        placeholder="{{ __('Add details, share a screenshot link, or follow up…') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep resize-y"></textarea>
                    <div class="flex items-center justify-between">
                        @if (in_array($ticket->status, ['resolved'], true))
                            <span
                                class="text-[11.5px] text-ink-500">{{ __('Replying will reopen this ticket.') }}</span>
                        @else
                            <span class="text-[11.5px] text-ink-500"></span>
                        @endif
                        <button
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Send reply') }}</button>
                    </div>
                </form>
            </section>
        @else
            <section class="bg-paper-50 border border-paper-200 rounded-2xl p-5 text-center text-[12.5px] text-ink-600">
                This ticket is closed. <a href="{{ url('/support') }}"
                    class="text-wa-deep font-semibold">{{ __('Open a new ticket') }}</a> if you need help.
            </section>
        @endif

        <div>
            <a href="{{ url('/support') }}" class="text-[12px] text-wa-deep hover:underline">← Back to all tickets</a>
        </div>

    </main>

</x-layouts.user>

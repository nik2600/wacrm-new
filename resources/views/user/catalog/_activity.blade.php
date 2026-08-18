<div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
    <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
        <div>
            <h3 class="font-serif text-[18px] leading-tight">{{ __('Catalog send activity') }}</h3>
            <div class="text-[11px] text-ink-500 mt-0.5">
                {{ __('Every catalog message your workspace has sent (SPM · MPM · link).') }}</div>
        </div>
        <a href="{{ route('user.catalog.send') }}"
            class="px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold">{{ __('Send another →') }}</a>
    </div>

    @if ($recentSends->isEmpty())
        <div class="px-5 py-12 text-center text-ink-500">
            <div class="font-serif text-[20px] text-ink-700">{{ __('No sends yet') }}</div>
            <div class="text-[12.5px] mt-2">{{ __('Send your first catalog from the') }} <a
                    href="{{ route('user.catalog.send') }}"
                    class="text-wa-deep font-semibold hover:underline">{{ __('Send tab') }}</a>.</div>
        </div>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                <tr>
                    <th class="px-5 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                        {{ __('When') }}</th>
                    <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">To</th>
                    <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                        {{ __('Mode') }}</th>
                    <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                        {{ __('Body') }}</th>
                    <th class="px-5 py-2.5 text-right font-mono text-[10px] uppercase tracking-[0.14em]"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-paper-200">
                @foreach ($recentSends as $r)
                    @php $modeMeta = $r->meta['mode'] ?? 'unknown'; @endphp
                    <tr>
                        <td class="px-5 py-3 font-mono text-[11px] text-ink-700">
                            {{ $r->created_at->format('M d, H:i') }}</td>
                        <td class="px-2 py-3 font-mono">{{ mask_phone($r->to_number) ?: '—' }}</td>
                        <td class="px-2 py-3">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono
 {{ $modeMeta === 'spm' ? 'bg-wa-mint text-wa-deep' : ($modeMeta === 'mpm' ? 'bg-accent-amber/15 text-accent-amber' : 'bg-paper-100 text-ink-700') }}">
                                {{ strtoupper($modeMeta) }}
                            </span>
                        </td>
                        <td class="px-2 py-3 text-[12px] text-ink-700 truncate max-w-md">{{ $r->body }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ url('/team-inbox') }}?conversation={{ $r->conversation_id }}"
                                class="text-[11.5px] text-wa-deep font-semibold hover:underline">View thread →</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

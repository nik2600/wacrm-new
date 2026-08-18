@forelse ($rows as $r)
    <tr class="hover:bg-paper-50/60">
        <td class="px-4 py-3 font-mono text-[11px] text-ink-700">
            <div>{{ $r['date'] }}</div>
            <div class="text-[10px] text-ink-500">{{ $r['human'] }}</div>
        </td>
        <td class="px-2 py-3">
            <div class="flex items-center gap-2.5">
                <span
                    class="w-7 h-7 rounded-full bg-gradient-to-br {{ $r['gradient'] }} text-paper-0 grid place-items-center text-[10px] font-semibold">{{ $r['refereeInitials'] }}</span>
                <div class="min-w-0">
                    <div class="font-medium truncate">{{ $r['refereeName'] }}</div>
                    <div class="text-[10.5px] text-ink-500 font-mono truncate">{{ $r['refereeEmail'] }}</div>
                </div>
            </div>
        </td>
        <td class="px-2 py-3 font-mono text-[11px] text-ink-700">{{ $r['codeUsed'] }}</td>
        <td class="px-2 py-3 font-mono text-[12px] text-wa-deep font-semibold">
            +{{ number_format($r['creditsAwarded']) }} {{ __('credits') }}</td>
        <td class="px-2 py-3">
            @if ($r['status'] === 'paid')
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Paid</span>
            @else
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-500 text-[10.5px] font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>No payout</span>
            @endif
        </td>
        <td class="px-4 py-3 text-right">
            @if ($r['walletTxId'])
                <span class="text-[10.5px] font-mono text-ink-500">tx-{{ $r['walletTxId'] }}</span>
            @else
                <span class="text-[10.5px] font-mono text-ink-500">—</span>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="px-4 py-4">
            @include('user.partials.empty-state', [
                'message' =>
                    'No referrals match the current filters. Share your link and new signups will appear here.',
                'resetHref' => url('/affiliate-history'),
            ])
        </td>
    </tr>
@endforelse

@php
    /**
     * Rendered as inner-HTML of #sched-tbody both at first page load and
     * after silent AJAX refreshes (?partial=1). The helpers re-declared
     * here let the partial render standalone — the controller's JSON
 * path can call view()->render() without re-defining them.
 */
use Illuminate\Support\Carbon;

/** @var \Illuminate\Support\Collection $rows */
$rows = $rows ?? collect();

$describeRecipients =
    $describeRecipients ??
    function ($r) {
        $count = (int) ($r->total_recipients ?? 0);
        $tag = match ($r->recipient_type) {
            'group' => 'Group',
            'queue' => 'Queue',
            'number' => 'Numbers',
            default => '—',
        };
        return number_format($count) . ' / ' . $tag;
    };

$statusPill =
    $statusPill ??
    function (string $status) {
        return match ($status) {
            'scheduled' => ['cls' => 'bg-wa-mint text-wa-deep', 'dot' => 'bg-wa-green', 'label' => 'Queued'],
            'running' => ['cls' => 'bg-[#D9E5F2] text-[#13478A]', 'dot' => 'bg-[#13478A]', 'label' => 'Running'],
            'paused' => [
                'cls' => 'bg-accent-amber/20 text-[#7B5A14]',
                'dot' => 'bg-accent-amber',
                'label' => 'Paused',
            ],
            'completed' => ['cls' => 'bg-paper-100 text-ink-700', 'dot' => 'bg-ink-500', 'label' => 'Done'],
            'failed' => [
                'cls' => 'bg-accent-coral/15 text-accent-coral',
                'dot' => 'bg-accent-coral',
                'label' => 'Failed',
            ],
            'cancelled' => ['cls' => 'bg-paper-100 text-ink-500', 'dot' => 'bg-ink-500/40', 'label' => 'Cancelled'],
            default => ['cls' => 'bg-paper-100 text-ink-700', 'dot' => 'bg-ink-500/40', 'label' => $status],
        };
    };

$typePill =
    $typePill ??
    function (string $type) {
        return match ($type) {
            'template' => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'icon' => 'M2.5 3.5h11v9h-11zM5 6h6M5 9h6'],
            'media' => ['cls' => 'bg-accent-amber/20 text-[#7B5A14]', 'icon' => 'M2 3h12v10H2zM5 7l3 3 5-5'],
            'location' => [
                'cls' => 'bg-[#F3E9FF] text-[#5B3D8A]',
                'icon' =>
                    'M8 1.5C5.5 1.5 3.5 3.4 3.5 5.7c0 3 4.5 8.8 4.5 8.8s4.5-5.8 4.5-8.8C12.5 3.4 10.5 1.5 8 1.5z',
            ],
            default => ['cls' => 'bg-paper-100 text-ink-700', 'icon' => 'M3 4h10M3 8h10M3 12h7'],
            };
        };
@endphp

@forelse ($rows as $row)
    @php
        $sp = $statusPill($row->status);
        $tp = $typePill($row->template_type);
        $tz = $row->timezone ?: 'UTC';
        $whenLocal = Carbon::parse($row->next_run_at ?: $row->scheduled_time)->setTimezone($tz);
        // Carbon 3 returns floats from diffInMinutes — cast to int or the
        // sub-line renders as "in 3.8237903166667m".
        $diff = (int) round(now()->diffInMinutes($whenLocal, false));
        $whenSub =
            $row->status === 'completed' && $row->completed_at
                ? 'completed ' . Carbon::parse($row->completed_at)->setTimezone($tz)->diffForHumans()
                : ($diff < 0
                        ? Carbon::parse($whenLocal)->diffForHumans()
                        : ($diff < 1
                            ? 'now'
                            : ($diff < 60
                                ? "in {$diff}m"
                                : 'in ' . (int) round($diff / 60) . 'h'))) .
                    ' · ' .
                    $tz;
        $repeatLbl = $row->is_recurring ? $row->repeat_interval : 'once';
    @endphp
    <tr class="sched-row hover:bg-paper-50/60" data-sched-row="{{ $row->id }}" data-sched-status="{{ $row->status }}"
        data-sched-name="{{ \Illuminate\Support\Str::lower($row->schedule_name) }}">
        <td class="px-4 py-3">
            <a href="{{ url('/scheduled/' . $row->id) }}" class="flex items-center gap-2.5 hover:underline">
                <span class="w-8 h-8 rounded-lg {{ $tp['cls'] }} grid place-items-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="{{ $tp['icon'] }}" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="font-semibold text-ink-900 truncate">{{ $row->schedule_name }}</div>
                    <div class="text-[10.5px] text-ink-500 font-mono truncate max-w-[260px]">
                        {{ \Illuminate\Support\Str::limit($row->message_content, 60) ?: '—' }}</div>
                </div>
            </a>
        </td>
        <td class="px-2 py-3">
            <span
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $tp['cls'] }} text-[10.5px] font-mono">{{ ucfirst($row->template_type) }}</span>
        </td>
        <td class="px-2 py-3 font-mono text-[11.5px] text-ink-700">{{ $describeRecipients($row) }}</td>
        <td class="px-2 py-3">
            <div class="font-mono text-[11.5px] text-ink-900">{{ $whenLocal->format('M j · H:i') }}</div>
            <div class="font-mono text-[10px] text-ink-500">{{ $whenSub }}</div>
        </td>
        <td class="px-2 py-3">
            @if ($row->is_recurring)
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[10.5px] font-mono">{{ $repeatLbl }}</span>
            @else
                <span class="text-[10.5px] text-ink-500 font-mono">{{ __('once') }}</span>
            @endif
        </td>
        <td class="px-2 py-3">
            <span
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $sp['cls'] }} text-[10.5px] font-mono">
                <span class="w-1.5 h-1.5 rounded-full {{ $sp['dot'] }}"></span>{{ $sp['label'] }}
            </span>
        </td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
            <a href="{{ url('/scheduled/' . $row->id) }}"
                class="w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep inline-flex items-center justify-center"
                title="{{ __('Open') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                    <circle cx="8" cy="8" r="2" />
                </svg>
            </a>
            @if (in_array($row->status, ['scheduled', 'running'], true))
                <button data-sched-action="pause" data-sched-id="{{ $row->id }}"
                    class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                    title="{{ __('Pause') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M5 3v10M11 3v10" />
                    </svg>
                </button>
            @elseif ($row->status === 'paused')
                <button data-sched-action="resume" data-sched-id="{{ $row->id }}"
                    class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                    title="{{ __('Resume') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M5 3l8 5-8 5z" />
                    </svg>
                </button>
            @endif
            @if (in_array($row->status, ['scheduled', 'paused', 'running'], true))
                <button data-sched-action="run-now" data-sched-id="{{ $row->id }}"
                    class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                    title="{{ __('Run now') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 14l13-6L2 2v5l9 1-9 1z" />
                    </svg>
                </button>
                <button data-sched-action="cancel" data-sched-id="{{ $row->id }}"
                    class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                    title="{{ __('Cancel') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
                {{-- Delete is only offered while the schedule is still
 actionable. Once it's completed/cancelled/failed the
 row collapses to just the eye — operator can still
 delete from the detail page if they really want. --}}
                <button data-sched-action="destroy" data-sched-id="{{ $row->id }}"
                    class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                    title="{{ __('Delete') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                    </svg>
                </button>
            @elseif ($row->status === 'failed')
                {{-- Retry icon next to the eye on failed rows. Clicking it
 opens a custom modal asking for a new send date / time /
 timezone, then resets the row to "scheduled" and
 re-registers it on Node. --}}
                <button data-sched-action="retry" data-sched-id="{{ $row->id }}"
                    class="w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep inline-flex items-center justify-center"
                    title="{{ __('Retry with new time') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                    </svg>
                </button>
            @endif
            @if (in_array($row->status, ['completed', 'cancelled', 'failed'], true))
                {{-- Terminal-state delete. Completed sends linger in the table
 forever otherwise — the operator needs a way to clean up
 old history without going to the detail page. Reuses the
 same `destroy` action wired by the JS so confirmation +
 row-removal logic stays unchanged. --}}
                <button data-sched-action="destroy" data-sched-id="{{ $row->id }}"
                    class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                    title="{{ __('Delete from history') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                    </svg>
                </button>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="px-4 py-4">
            @include('user.partials.empty-state', [
                'message' =>
                    'No schedules match the current filters. Try clearing filters or schedule your first message.',
                'resetHref' => url('/scheduled'),
                'actionHref' => route('user.scheduled.create'),
                'actionLabel' => 'Schedule message',
            ])
        </td>
    </tr>
@endforelse

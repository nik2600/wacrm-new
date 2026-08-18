<x-layouts.admin :title="__('Customer · ' . $workspace->name)" admin-key="support-customers" page="admin-support-customer-show">

<header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
 <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
 <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
 <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
 <a href="{{ url('/admin/support') }}" class="hover:text-ink-900">{{ __('Support') }}</a>
 <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
 <a href="{{ url('/admin/support/customers') }}" class="hover:text-ink-900">{{ __('Customers') }}</a>
 <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
 <span class="text-ink-900 normal-case tracking-normal truncate max-w-[300px]">{{ $workspace->name }}</span>
 </div>
 <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
</header>

<main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

 <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
 <div class="min-w-0">
 <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Support · Customer') }}</div>
 <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0] break-words">{{ $workspace->name }}</h1>
 <p class="text-[13px] text-ink-600 mt-2">{{ __('All tickets, response stats, and quick links for this customer workspace.') }}</p>
 </div>
 <div class="flex items-center flex-wrap gap-2">
 <a href="{{ url('/admin/workspaces/' . $workspace->id) }}" class="px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">Workspace settings</a>
 <a href="{{ route('admin.support.customers') }}" class="px-3.5 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All customers') }}</a>
 </div>
 </div>

 <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
 <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ __('Total tickets') }}</div>
 <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['total'] }}</div>
 </div>
 <div class="bg-paper-0 border border-{{ $stats['open'] > 0 ? 'accent-coral/40' : 'paper-200' }} rounded-2xl p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ __('Open') }}</div>
 <div class="font-serif text-[34px] leading-none mt-1 {{ $stats['open'] > 0 ? 'text-accent-coral' : '' }}">{{ $stats['open'] }}</div>
 </div>
 <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ __('Resolved') }}</div>
 <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['resolved'] }}</div>
 </div>
 <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ __('Avg 1st reply') }}</div>
 <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['avg_first_resp'] ?: '—' }}<span class="text-[14px] text-ink-500">m</span></div>
 </div>
 <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ __('Avg resolution') }}</div>
 <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['avg_resolution'] ?: '—' }}<span class="text-[14px] text-ink-500">m</span></div>
 </div>
 </section>

 <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-5 py-4 border-b border-paper-200">
 <h2 class="font-serif text-[20px]">{{ __('Ticket history') }}</h2>
 </div>
 <div class="overflow-x-auto">
 <table class="w-full text-[12.5px] table-fixed min-w-[860px]">
 <thead class="bg-paper-50 text-ink-500 text-left">
 <tr>
 <th class="px-3 py-2.5 w-[108px] font-medium">{{ __('When') }}</th>
 <th class="px-3 py-2.5 font-medium">{{ __('Subject') }}</th>
 <th class="px-2 py-2.5 w-[110px] font-medium">{{ __('Status') }}</th>
 <th class="px-2 py-2.5 w-[90px] text-center font-medium">{{ __('Priority') }}</th>
 <th class="px-2 py-2.5 w-[140px] font-medium">{{ __('Assigned') }}</th>
 <th class="px-2 py-2.5 w-[100px] text-right font-medium">{{ __('Resolved in') }}</th>
 <th class="px-3 py-2.5 w-[70px] text-right font-medium"></th>
 </tr>
 </thead>
 <tbody class="divide-y divide-paper-200">
 @forelse ($tickets as $t)
 @php
 $prCls = ['urgent'=>'bg-accent-coral/15 text-accent-coral border-accent-coral/30','high'=>'bg-accent-amber/15 text-accent-amber border-accent-amber/30','normal'=>'bg-paper-100 text-ink-700 border-paper-200','low'=>'bg-paper-50 text-ink-500 border-paper-200'][$t->priority] ?? '';
 $stCls = ['open'=>'bg-accent-amber/15 text-accent-amber','in_progress'=>'bg-wa-bubble text-wa-deep','pending'=>'bg-accent-coral/10 text-accent-coral','resolved'=>'bg-wa-mint text-wa-deep','closed'=>'bg-paper-100 text-ink-600'][$t->status] ?? '';
 $resolveMin = ($t->resolved_at && $t->created_at)
 ? \Carbon\Carbon::parse($t->created_at)->diffInMinutes(\Carbon\Carbon::parse($t->resolved_at))
 : null;
 @endphp
 <tr class="hover:bg-paper-50/60">
 <td class="px-3 py-3 font-mono text-[11px] align-top">{{ optional($t->created_at)->format('M j Y') }}<br><span class="text-ink-500">{{ optional($t->created_at)->format('H:i') }}</span></td>
 <td class="px-3 py-3 align-top">
 <div class="font-semibold truncate">{{ $t->subject ?: '(no subject)' }}</div>
 <div class="text-[10.5px] text-ink-500 font-mono truncate">#{{ $t->ticket_number }} · {{ ($users[$t->user_id] ?? null)?->name ?? $t->name }}</div>
 </td>
 <td class="px-2 py-3 align-top"><span class="px-2 py-0.5 rounded-full {{ $stCls }} text-[10.5px] font-mono">{{ str_replace('_',' ', $t->status) }}</span></td>
 <td class="px-2 py-3 align-top text-center"><span class="px-2 py-0.5 rounded-full border text-[10px] font-mono uppercase {{ $prCls }}">{{ $t->priority }}</span></td>
 <td class="px-2 py-3 align-top text-[11.5px]">{{ ($users[$t->assigned_agent_id] ?? null)?->name ?? '—' }}</td>
 <td class="px-2 py-3 align-top text-right font-mono text-[11px]">{{ $resolveMin !== null ? $resolveMin . 'm' : '—' }}</td>
 <td class="px-3 py-3 align-top text-right"><a href="{{ route('admin.support.index', ['open' => $t->id]) }}" class="text-wa-deep text-[11px] hover:underline">Open →</a></td>
 </tr>
 @empty
 <tr><td colspan="7" class="px-4 py-12 text-center text-ink-500 text-[13px]">{{ __("This workspace hasn't raised any tickets.") }}</td></tr>
 @endforelse
 </tbody>
 </table>
 </div>
 </section>

</main>

</x-layouts.admin>

<x-layouts.admin :title="__('Support inbox')" admin-key="support" page="admin-support-index">

<header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
 <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
 <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
 <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
 <span class="text-ink-900 normal-case tracking-normal">{{ __('Support inbox') }}</span>
 </div>
 <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
</header>

<main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

 <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
 <div>
 <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Support · Inbox') }}</div>
 <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Support') }} <span class="italic text-wa-deep">{{ __('inbox') }}</span></h1>
 <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">{{ __('Every customer ticket lands here. Click a row to open the thread, reply, assign, or change priority.') }}</p>
 </div>
 <div class="flex items-center gap-2">
 <a href="{{ url('/admin/support/team-inbox') }}" class="px-3.5 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Kanban view') }}</a>
 </div>
 </div>

 @if (session('success'))<div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>@endif

 <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
 @foreach ([
 ['Open', $kpi['open'], 'wa-green/40'],
 ['Unassigned', $kpi['unassigned'], 'accent-coral/40'],
 ['Resolved 24h', $kpi['resolved_24h'], 'paper-200'],
 ['All-time', $kpi['total'], 'paper-200'],
 ] as [$label, $val, $border])
 <div class="bg-paper-0 border border-{{ $border }} rounded-2xl p-4 shadow-card">
 <div class="text-[11px] text-ink-600 font-medium">{{ $label }}</div>
 <div class="font-serif text-[34px] leading-none mt-1">{{ $val }}</div>
 </div>
 @endforeach
 </section>

 <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <form method="GET" action="{{ route('admin.support.index') }}" class="px-5 py-3 border-b border-paper-200 flex items-center gap-2 flex-wrap">
 <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[12px]">
 <option value="">{{ __('All status') }}</option>
 @foreach (['open','in_progress','pending','resolved','closed'] as $s)
 <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_',' ', $s)) }}</option>
 @endforeach
 </select>
 <select name="priority" onchange="this.form.submit()" class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[12px]">
 <option value="">{{ __('All priority') }}</option>
 @foreach (['urgent','high','normal','low'] as $p)
 <option value="{{ $p }}" @selected($priority === $p)>{{ ucfirst($p) }}</option>
 @endforeach
 </select>
 <select name="agent" onchange="this.form.submit()" class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[12px]">
 <option value="">{{ __('All agents') }}</option>
 <option value="me" @selected($agent === 'me')>{{ __('Assigned to me') }}</option>
 <option value="unassigned" @selected($agent === 'unassigned')>{{ __('Unassigned') }}</option>
 </select>
 <div class="relative flex-1 max-w-[300px]">
 <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search ticket / customer…') }}" class="w-full px-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0">
 </div>
 @if ($status || $priority || $agent || $q)
 <a href="{{ route('admin.support.index') }}" class="text-[11px] text-ink-500 hover:text-wa-deep">{{ __('Clear') }}</a>
 @endif
 </form>

 <div class="overflow-x-auto">
 <table class="w-full text-[12.5px]">
 <thead class="bg-paper-50 text-ink-500 text-left">
 <tr>
 <th class="px-4 py-2.5 w-[110px] font-medium">{{ __('When') }}</th>
 <th class="px-3 py-2.5 font-medium">{{ __('Subject / Customer') }}</th>
 <th class="px-3 py-2.5 w-[90px] text-center font-medium">{{ __('Priority') }}</th>
 <th class="px-3 py-2.5 w-[120px] font-medium">{{ __('Status') }}</th>
 <th class="px-3 py-2.5 w-[140px] font-medium">{{ __('Assignee') }}</th>
 <th class="px-4 py-2.5 w-[60px] text-right font-medium"></th>
 </tr>
 </thead>
 <tbody class="divide-y divide-paper-200">
 @forelse ($tickets as $t)
 @php
 $prCls = ['urgent'=>'bg-accent-coral/15 text-accent-coral border-accent-coral/30','high'=>'bg-accent-amber/15 text-accent-amber border-accent-amber/30','normal'=>'bg-paper-100 text-ink-700 border-paper-200','low'=>'bg-paper-50 text-ink-500 border-paper-200'][$t->priority] ?? '';
 $stCls = ['open'=>'bg-accent-amber/15 text-accent-amber','in_progress'=>'bg-wa-bubble text-wa-deep','pending'=>'bg-accent-coral/10 text-accent-coral','resolved'=>'bg-wa-mint text-wa-deep','closed'=>'bg-paper-100 text-ink-600'][$t->status] ?? '';
 @endphp
 <tr class="hover:bg-paper-50/60 cursor-pointer" data-support-row data-ticket-id="{{ $t->id }}">
 <td class="px-4 py-3 font-mono text-[11px] align-top">{{ optional($t->created_at)->format('M j') }}<br><span class="text-ink-500">{{ optional($t->created_at)->format('H:i') }}</span></td>
 <td class="px-3 py-3">
 <div class="font-semibold truncate">{{ $t->subject ?: '(no subject)' }}</div>
 <div class="text-[10.5px] text-ink-500 font-mono truncate">#{{ $t->ticket_number }} · {{ $t->name ?: $t->email }}</div>
 </td>
 <td class="px-3 py-3 text-center"><span class="px-2 py-0.5 rounded-full border text-[10px] font-mono uppercase {{ $prCls }}">{{ $t->priority }}</span></td>
 <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full {{ $stCls }} text-[10.5px] font-mono">{{ str_replace('_', ' ', $t->status) }}</span></td>
 <td class="px-3 py-3 text-[11.5px]">
 @if ($t->assigned_agent_id)
 {{ ($agentUsers[$t->assigned_agent_id] ?? null)?->name ?? 'User #' . $t->assigned_agent_id }}
 @else
 <span class="text-ink-500 italic">{{ __('unassigned') }}</span>
 @endif
 </td>
 <td class="px-4 py-3 text-right"><span class="text-wa-deep text-[11px]">{{ __('Open →') }}</span></td>
 </tr>
 @empty
 <tr><td colspan="6" class="px-4 py-12 text-center text-ink-500 text-[13px]">{{ __('No tickets match the current filters.') }}</td></tr>
 @endforelse
 </tbody>
 </table>
 </div>

 <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40">
 {{ $tickets->links() }}
 </div>
 </section>

</main>

{{-- Slide-in detail panel.
 Each action is a tiny inline form posting to the matching route.
 After submit the page reloads with ?open={id} so the panel re-opens
 on the same ticket — no SPA / AJAX layer needed. --}}
@php $openId = (int) request('open', 0); @endphp
<aside id="support-panel" class="fixed inset-y-0 right-0 w-[520px] max-w-full {{ $openId ? '' : 'translate-x-full' }} transition-transform duration-200 z-50 shadow-2xl bg-paper-0 border-l border-paper-200 flex flex-col" data-support-panel data-open-id="{{ $openId }}">
 <header class="px-5 py-3 border-b border-paper-200 flex items-center justify-between shrink-0">
 <div class="min-w-0 flex-1">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Ticket') }} <span data-panel-number></span></div>
 <div class="font-serif text-[18px] truncate" data-panel-subject>—</div>
 </div>
 <button type="button" data-panel-close class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>
 </header>

 {{-- Quick actions row: status / priority / assign --}}
 <div class="px-5 py-3 border-b border-paper-200 space-y-2 shrink-0">
 <div class="flex items-center gap-2 flex-wrap" data-panel-actions>
 {{-- Status pills --}}
 <div class="flex items-center gap-1" data-panel-action-group="status">
 @foreach (['open','in_progress','pending','resolved','closed'] as $s)
 @php $stCls = ['open'=>'bg-accent-amber/15 text-accent-amber','in_progress'=>'bg-wa-bubble text-wa-deep','pending'=>'bg-accent-coral/10 text-accent-coral','resolved'=>'bg-wa-mint text-wa-deep','closed'=>'bg-paper-100 text-ink-600'][$s]; @endphp
 <form method="POST" data-panel-mini-form data-action="status" class="inline">
 @csrf
 <input type="hidden" name="status" value="{{ $s }}">
 <button class="px-2 py-0.5 rounded-full {{ $stCls }} text-[10px] font-mono hover:ring-2 hover:ring-wa-deep/30" type="submit">{{ str_replace('_',' ', $s) }}</button>
 </form>
 @endforeach
 </div>
 </div>
 <div class="flex items-center gap-2 flex-wrap">
 <div class="flex items-center gap-1" data-panel-action-group="priority">
 <span class="text-[10px] font-mono text-ink-500 uppercase">{{ __('Priority:') }}</span>
 @foreach (['low','normal','high','urgent'] as $p)
 @php $prCls = ['urgent'=>'bg-accent-coral/15 text-accent-coral border-accent-coral/30','high'=>'bg-accent-amber/15 text-accent-amber border-accent-amber/30','normal'=>'bg-paper-100 text-ink-700 border-paper-200','low'=>'bg-paper-50 text-ink-500 border-paper-200'][$p]; @endphp
 <form method="POST" data-panel-mini-form data-action="priority" class="inline">
 @csrf
 <input type="hidden" name="priority" value="{{ $p }}">
 <button class="px-2 py-0.5 rounded-full border {{ $prCls }} text-[10px] font-mono uppercase hover:ring-2 hover:ring-wa-deep/30" type="submit">{{ $p }}</button>
 </form>
 @endforeach
 </div>
 </div>
 <form method="POST" data-panel-mini-form data-action="assign" class="flex items-center gap-2">
 @csrf
 <span class="text-[11px] text-ink-600 shrink-0">{{ __('Assign to') }}</span>
 <select name="agent_user_id" class="flex-1 px-2 py-1 border border-paper-200 rounded-lg bg-white text-[11.5px] focus:outline-none focus:border-wa-deep">
 <option value="">— unassigned —</option>
 @foreach ($agents as $a)
 <option value="{{ $a->user_id }}">{{ $a->user?->name ?? 'User #' . $a->user_id }}</option>
 @endforeach
 </select>
 <button type="submit" class="px-2.5 py-1 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11px] font-semibold">{{ __('Save') }}</button>
 </form>
 @if ($playbooks->count())
 <form method="POST" data-panel-playbook class="flex items-center gap-2">
 @csrf
 <span class="text-[11px] text-ink-600 shrink-0">{{ __('Run playbook') }}</span>
 <select name="playbook_id" class="flex-1 px-2 py-1 border border-paper-200 rounded-lg bg-white text-[11.5px] focus:outline-none focus:border-wa-deep">
 <option value="">— pick playbook —</option>
 @foreach ($playbooks as $p)
 <option value="{{ $p->id }}">{{ $p->name }}</option>
 @endforeach
 </select>
 <button type="submit" class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px] font-semibold">{{ __('Run') }}</button>
 </form>
 @endif
 <div class="text-[10.5px] font-mono text-ink-500 grid grid-cols-2 gap-1 pt-1 border-t border-paper-200" data-panel-sla>
 <span class="text-ink-500">{{ __('SLA · 1st reply:') }}</span><span data-sla-first>—</span>
 <span class="text-ink-500">{{ __('SLA · resolution:') }}</span><span data-sla-resolution>—</span>
 </div>
 </div>

 {{-- Customer / workspace meta --}}
 <div class="px-5 py-3 border-b border-paper-200 text-[11.5px] grid grid-cols-2 gap-2" data-panel-meta>
 <div class="text-ink-500">{{ __('Loading…') }}</div>
 </div>

 {{-- Thread --}}
 <div class="flex-1 overflow-y-auto p-5 space-y-3" data-panel-thread></div>

 {{-- Reply box --}}
 <form data-panel-reply method="POST" class="border-t border-paper-200 p-3 space-y-2 shrink-0">
 @csrf
 <textarea name="body" rows="3" placeholder="{{ __('Reply to customer or add an internal note…') }}" class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep resize-none"></textarea>
 <div class="flex items-center justify-between gap-2">
 <label class="flex items-center gap-1.5 text-[11px]">
 <input type="checkbox" name="is_internal_note" value="1" class="rounded border-paper-200">
 Internal note (not sent to customer)
 </label>
 <button type="submit" class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Send reply') }}</button>
 </div>
 </form>
</aside>

</x-layouts.admin>

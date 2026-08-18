<x-layouts.user :title="__('Contacts')" nav-key="contacts" page="user-contacts-index">
    <script>
        window.WADESK_CONTACT_DESTROY_TEMPLATE = @json(route('user.contacts.destroy', ':id'));
        window.WADESK_GROUP_DESTROY_TEMPLATE = @json(route('user.contact-groups.destroy', ':id'));
        window.WADESK_CONTACTS_URL = @json(route('user.contacts'));
        window.WADESK_CONTACT_BULK_EXPORT_URL = @json(route('user.contacts.bulk-export'));
        window.WADESK_CONTACT_BULK_GROUP_URL = @json(route('user.contacts.bulk-group'));
        window.WADESK_CONTACT_BULK_TAG_URL = @json(route('user.contacts.bulk-tag'));
        window.WADESK_CONTACT_DEDUPE_URL = @json(route('user.contacts.dedupe'));
        window.WADESK_GROUPS = @json($groups->map(fn ($g) => ['id' => (string) $g->id, 'name' => $g->user_group, 'color' => $g->color ?? '#075E54'])->values());
        // Active list filter + page/total counts — powers "select all N
        // matching this filter" so a bulk action can reach the WHOLE book,
        // not just the rows rendered on the current page.
        window.WADESK_CONTACT_FILTER = {
            q: @json($searchQ ?? ''),
            group: @json($groupSel ?? ''),
            tag: @json(request('tag', '')),
            total: {{ (int) $contacts->total() }},
            shown: {{ (int) $contacts->count() }},
        };
    </script>

    @if (session('status') || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    @if (session('status'))
                        window.toast(@json(session('status')), 'success');
                    @endif
                    @foreach ($errors->all() as $err)
                        window.toast(@json($err), 'error');
                    @endforeach
                });
            </script>
        @endpush
    @endif

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            <aside class="lg:sticky lg:top-6 self-start space-y-3">
                <div
                    class="hairline border border-paper-200 rounded-2xl bg-wa-bubble/40 p-3 text-[11px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor">
                            <circle cx="8" cy="8" r="6" />
                        </svg>
                        Tip
                    </div>
                    {{ __('Keep phone numbers with country codes and group contacts by audience before launching broadcasts or flows.') }}
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-2">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Contact views') }}</div>
                    <button data-tab-target="contacts"
                        class="side-tab w-full flex items-center justify-between px-3 py-2 rounded-xl bg-wa-deep text-paper-0 text-[13px] font-medium">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-wa-green"></span>All
                            contacts</span>
                        <span class="mono font-mono text-[11px]">{{ number_format($stats['all_contacts']) }}</span>
                    </button>
                    <button data-tab-target="groups"
                        class="side-tab w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-accent-amber"></span>Groups</span>
                        <span
                            class="mono font-mono text-[11px] text-ink-500">{{ number_format($stats['groups_total']) }}</span>
                    </button>
                    <button type="button" data-source-filter="import"
                        class="source-filter w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-[#13478A]"></span>Imported</span>
                        <span
                            class="mono font-mono text-[11px] text-ink-500">{{ number_format($stats['imported']) }}</span>
                    </button>
                    <button type="button" data-source-filter="no_group"
                        class="source-filter w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-paper-200"></span>No
                            group</span>
                        <span
                            class="mono font-mono text-[11px] text-ink-500">{{ number_format($stats['no_group']) }}</span>
                    </button>
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-2">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Groups') }}</div>
                    <button data-group-filter="all"
                        class="group-filter w-full flex items-center justify-between px-3 py-2 rounded-xl bg-paper-50 text-ink-900 text-[13px] font-medium">
                        <span>{{ __('All groups') }}</span><span
                            class="mono font-mono text-[11px]">{{ number_format($stats['grouped_total']) }}</span>
                    </button>
                    @foreach ($groupsWithCounts as $group)
                        <button data-group-filter="{{ $group->id }}" data-filter-group="{{ $group->id }}"
                            class="group-filter w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                            <span class="flex items-center gap-2"><span class="group-dot w-[7px] h-[7px] rounded-full"
                                    style="background:{{ $group->color ?: 'var(--color-wa-deep)' }}"></span>{{ $group->user_group }}</span><span
                                class="mono font-mono text-[11px] text-ink-500">{{ number_format($group->members_count) }}</span>
                        </button>
                    @endforeach
                </div>

                <div
                    class="card bg-white border border-paper-200 rounded-[14px] shadow-card bg-wa-bubble/40 p-3 text-[11px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor">
                            <circle cx="8" cy="8" r="6" />
                        </svg>
                        Import format
                    </div>
                    {{ __('Bulk uploads expect contact name, country code, mobile number, and optional email or group columns.') }}
                </div>
            </aside>

            <section>
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between mb-5 gap-4">
                    <div class="min-w-0">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }} · {{ auth()->user()?->currentWorkspace?->name ?: __('Workspace') }}</div>
                        <h1
                            class="serif font-serif font-normal tracking-[-0.01em] text-[32px] sm:text-[38px] lg:text-[44px] leading-[1.0] tracking-tight">
                            {{ __('Your') }} <span class="italic text-wa-deep">{{ __('contacts') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __('Manage people, groups, imports, and contact details from one operational view.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        <button type="button" id="dedupeBtn"
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2"
                            title="{{ __('Remove duplicate phone numbers, keeping the earliest of each') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M5 5h7v7M5 9h3v3M3 3h2v2" />
                            </svg>
                            {{ __('Remove duplicates') }}
                        </button>
                        {{-- Export ALL contacts in the current view (not just the
                             12 on-screen / selected rows). Submits #exportAllForm. --}}
                        <button type="submit" form="exportAllForm"
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2"
                            title="{{ __('Download every contact in this view as a CSV') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                            </svg>
                            {{ __('Export all') }}
                        </button>
                        <button data-modal-open="bulkModal"
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M8 3v8M5 6l3-3 3 3M3 13h10" />
                            </svg>
                            Bulk upload
                        </button>
                        <button data-modal-open="groupModal"
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <circle cx="6" cy="6" r="3" />
                                <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5M12 6v5M9.5 8.5h5" />
                            </svg>
                            New group
                        </button>
                        <button data-modal-open="contactModal"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            Add contact
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Total contacts') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1">
                            {{ number_format($stats['total_contacts']) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2">{{ __('in your workspace') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Groups') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1">
                            {{ number_format($stats['total_groups']) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2">{{ __('segments configured') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Subscribed') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['subscribed']) }}
                        </div>
                        <div class="text-[11px] text-ink-500 mt-2">{{ __('opted in') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Unsubscribed') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1">
                            {{ number_format($stats['unsubscribed']) }}</div>
                        <div class="text-[11px] text-ink-500 mt-2">{{ __('opted out') }}</div>
                    </div>
                </div>

                <div
                    class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-2 flex items-center gap-1 mb-3 flex-wrap">
                    <button data-tab-target="contacts"
                        class="top-tab filter-tab inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-wa-deep [&.active]:text-paper-0 active">{{ __('All contacts') }}
                        <span class="mono font-mono text-[11px] opacity-80"
                            data-visible-count>{{ $contacts->total() }}</span></button>
                    <button data-tab-target="groups"
                        class="top-tab filter-tab inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-wa-deep [&.active]:text-paper-0">{{ __('Groups') }}
                        <span class="mono font-mono text-[11px] opacity-80">{{ number_format($stats['total_groups']) }}</span></button>
                    <div class="hidden sm:block flex-1"></div>
                    {{-- Bulk-action toolbar — shown only when one or more rows
                         are selected. Gives the operator full control over the
                         selection (export / add to group / remove from group /
                         delete) instead of just delete. --}}
                    <div id="bulkBar" class="hidden items-center gap-1.5 flex-wrap relative">
                        <span class="mono font-mono text-[11px] text-ink-500"><span
                                data-bulk-count>0</span> {{ __('selected') }}</span>
                        <button type="button" id="bulkExportBtn"
                            class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-700 text-[12px] font-medium hover:bg-paper-50">{{ __('Export') }}</button>
                        <button type="button" id="bulkAddGroupBtn"
                            class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-700 text-[12px] font-medium hover:bg-paper-50">{{ __('Add to group') }}</button>
                        <button type="button" id="bulkRemoveGroupBtn"
                            class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-700 text-[12px] font-medium hover:bg-paper-50">{{ __('Remove from group') }}</button>
                        <button type="button" id="bulkTagBtn"
                            class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-700 text-[12px] font-medium hover:bg-paper-50">{{ __('Tag') }}</button>
                        <button type="button" id="bulkUntagBtn"
                            class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-700 text-[12px] font-medium hover:bg-paper-50">{{ __('Untag') }}</button>
                        <button id="deleteSelected"
                            class="px-3 py-1.5 rounded-full border border-accent-coral text-accent-coral text-[12px] font-medium hover:bg-accent-coral/10">{{ __('Delete') }}</button>
                        {{-- Bulk tag picker. Pick an existing tag OR type a new
                             name — the endpoint firstOrCreate()s it, same as the
                             per-contact tag input, so there is one way to make a
                             tag in this app, not two. --}}
                        <div id="bulkTagPicker"
                            class="hidden absolute top-full right-0 mt-1 z-20 w-72 bg-white border border-paper-200 rounded-xl shadow-card p-3 space-y-2 text-left">
                            <div class="text-[11px] font-semibold text-ink-700" data-bulk-tag-title>
                                {{ __('Add tag') }}</div>
                            <select id="bulkTagSelect"
                                class="w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                                @forelse ($tags as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}
                                        ({{ $tagCounts[$t->id] ?? 0 }})</option>
                                @empty
                                    <option value="">{{ __('No tags yet — type one below') }}</option>
                                @endforelse
                            </select>
                            <div data-bulk-tag-new>
                                <input id="bulkTagNew" type="text" maxlength="80"
                                    placeholder="{{ __('…or type a new tag name') }}"
                                    class="w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" id="bulkTagCancel"
                                    class="px-3 py-1.5 rounded-full border border-paper-200 text-[11px] font-medium hover:bg-paper-50">{{ __('Cancel') }}</button>
                                <button type="button" id="bulkTagApply"
                                    class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal">{{ __('Apply') }}</button>
                            </div>
                        </div>
                        <div id="bulkGroupPicker"
                            class="hidden absolute top-full right-0 mt-1 z-20 w-64 bg-white border border-paper-200 rounded-xl shadow-card p-3 space-y-2 text-left">
                            <div class="text-[11px] font-semibold text-ink-700" data-bulk-group-title>
                                {{ __('Add to group') }}</div>
                            <select id="bulkGroupSelect"
                                class="w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                                @foreach ($groups as $g)
                                    <option value="{{ $g->id }}">{{ $g->user_group ?: __('Group #:id', ['id' => $g->id]) }}</option>
                                @endforeach
                            </select>
                            <div class="flex justify-end gap-2">
                                <button type="button" id="bulkGroupCancel"
                                    class="px-3 py-1.5 rounded-full border border-paper-200 text-[11px] font-medium hover:bg-paper-50">{{ __('Cancel') }}</button>
                                <button type="button" id="bulkGroupApply"
                                    class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal">{{ __('Apply') }}</button>
                            </div>
                        </div>
                    </div>
                    {{-- Search + group filter. JS debounces the search box and
                         submits this GET form so it queries EVERY contact (not
                         just the 12 on the page). The group dropdown + the
                         sidebar group list navigate to ?group=<id>. --}}
                    <form method="GET" action="{{ route('user.contacts') }}" class="contents" id="contactFilterForm">
                        {{-- Preserve the chosen page size when searching / changing group. --}}
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <div class="relative w-full sm:w-72">
                            <svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                                stroke="currentColor" stroke-width="1.5">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input id="contactSearch" name="q" value="{{ $searchQ ?? '' }}"
                                placeholder="{{ __('Search name, email, phone...') }}"
                                class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full focus:outline-none focus:border-wa-deep" />
                        </div>
                        <select id="groupSelect" name="group" onchange="this.form.submit()"
                            class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 focus:outline-none focus:border-wa-deep">
                            <option value="all" @selected(($groupSel ?? '') === '' || ($groupSel ?? '') === 'all')>{{ __('All groups') }}</option>
                            <option value="no_group" @selected(($groupSel ?? '') === 'no_group')>{{ __('No group') }}</option>
                            @foreach ($groups as $g)
                                <option value="{{ $g->id }}" @selected((string) ($groupSel ?? '') === (string) $g->id)>{{ $g->user_group ?: __('Group #:id', ['id' => $g->id]) }}</option>
                            @endforeach
                        </select>
                    </form>
                    {{-- Page size — the book can hit thousands of rows, so let the
                         operator show more per page (and bulk-select far more than the
                         default 12 at once). Its own GET form so it preserves the active
                         search / group / tag filter without any JS. --}}
                    <form method="GET" action="{{ route('user.contacts') }}" class="contents" id="pageSizeForm">
                        <input type="hidden" name="q" value="{{ $searchQ ?? '' }}">
                        <input type="hidden" name="group" value="{{ $groupSel ?? '' }}">
                        <input type="hidden" name="tag" value="{{ request('tag', '') }}">
                        <select name="per_page" onchange="this.form.submit()" aria-label="{{ __('Rows per page') }}"
                            class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 focus:outline-none focus:border-wa-deep">
                            @foreach ($allowedPerPage as $sz)
                                <option value="{{ $sz }}" @selected((int) $perPage === (int) $sz)>{{ $sz }} / {{ __('page') }}</option>
                            @endforeach
                        </select>
                    </form>
                    <x-list-grid-toggle target="contacts" />
                </div>

                {{-- Hidden form for "Export" — a normal POST so the browser
                     downloads the CSV. JS fills in the selected ids then submits. --}}
                <form method="POST" action="{{ route('user.contacts.bulk-export') }}" id="bulkExportForm" class="hidden">
                    @csrf
                    <div id="bulkExportIds"></div>
                </form>

                {{-- "Export all" — full-book CSV honouring the ACTIVE group / tag /
                     search filter (server-rendered, since those reload the page).
                     `all=1` tells bulkExport to ignore the selection + paginate. --}}
                <form method="POST" action="{{ route('user.contacts.bulk-export') }}" id="exportAllForm" class="hidden">
                    @csrf
                    <input type="hidden" name="all" value="1">
                    <input type="hidden" name="group" value="{{ $groupSel ?? '' }}">
                    <input type="hidden" name="tag" value="{{ request('tag', '') }}">
                    <input type="hidden" name="q" value="{{ $searchQ ?? '' }}">
                </form>

                {{-- Select-all-matching — ticking the header checkbox only selects
                     the rows ON THIS PAGE. This bar extends the selection to EVERY
                     contact matching the current filter so "add all N to a group" is
                     a single action. Only rendered when there is more than one page. --}}
                @if ($contacts->total() > $contacts->count())
                    <div id="selectAllMatchingBar"
                        class="hidden items-center justify-center gap-2 text-[12px] text-ink-700 bg-wa-bubble/40 border border-paper-200 rounded-lg px-3 py-2 mb-3">
                        <span data-sam-idle>
                            {{ __('All :n on this page selected.', ['n' => number_format($contacts->count())]) }}
                            <button type="button" id="selectAllMatchingBtn"
                                class="font-semibold text-wa-deep underline underline-offset-2">{{ __('Select all :total matching this filter', ['total' => number_format($contacts->total())]) }}</button>
                        </span>
                        <span data-sam-active class="hidden">
                            {{ __('All :total contacts matching this filter are selected.', ['total' => number_format($contacts->total())]) }}
                            <button type="button" id="selectAllMatchingClear"
                                class="font-semibold text-wa-deep underline underline-offset-2">{{ __('Clear selection') }}</button>
                        </span>
                    </div>
                @endif

                <div id="contactsPanel" data-panel="contacts"
                    class="card bg-white border border-paper-200 rounded-[14px] shadow-card overflow-hidden"
                    data-list-grid data-list-grid-key="contacts">
                    <div class="overflow-x-auto" data-list-grid-list>
                        <table class="w-full min-w-[1100px] text-sm table-fixed" data-list-grid-source>
                            <colgroup>
                                <col class="w-[42px]">
                                <col class="w-[220px]">
                                <col class="w-[200px]">
                                <col class="w-[210px]">
                                <col class="w-[140px]">
                                <col>
                                <col class="w-[96px]">
                            </colgroup>
                            <thead class="bg-paper-50 hairline-b border-b border-paper-200">
                                <tr class="text-left text-[11px] uppercase tracking-[0.14em] text-ink-500">
                                    <th class="py-2.5 pl-4 pr-2"><input id="selectAll" type="checkbox"></th>
                                    <th class="py-2.5 px-3 font-semibold">{{ __('Name') }}</th>
                                    <th class="py-2.5 px-3 font-semibold">{{ __('Groups') }}</th>
                                    <th class="py-2.5 px-3 font-semibold">{{ __('Tags') }}</th>
                                    <th class="py-2.5 px-3 font-semibold">{{ __('Email') }}</th>
                                    <th class="py-2.5 px-3 font-semibold">{{ __('Mobile') }}</th>
                                    <th class="py-2.5 px-3 font-semibold">{{ __('Memo') }}</th>
                                    <th class="py-2.5 pr-4 pl-3 font-semibold text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody id="contactsBody" class="divide-y divide-paper-100">
                                @forelse ($contacts as $contact)
                                    @php
                                        $contactGroupIds = is_array($contact->contact_group)
                                            ? $contact->contact_group
                                            : [];
                                        $contactGroupModels = $groups->whereIn(
                                            'id',
                                            array_map('intval', $contactGroupIds),
                                        );
                                        $groupSlugs = $contactGroupModels
                                            ->pluck('id')
                                            ->map(fn($i) => 'g' . $i)
                                            ->implode(' ');
                                        $groupIdsCsv = implode(',', array_map('strval', $contactGroupIds));
                                        $searchKey = strtolower(
                                            trim(
                                                ($contact->name ?? '') .
                                                    ' ' .
                                                    ($contact->email ?? '') .
                                                    ' ' .
                                                    ($contact->mobile ?? ''),
                                            ),
                                        );
                                        $initials = \Illuminate\Support\Str::of($contact->name ?? '?')
                                            ->trim()
                                            ->limit(2, '')
                                            ->upper();
                                        $avatarBg = ['#FFE9E4', '#E8F5E9', '#FFF6E0', '#F3E9FF', '#EEF4FF', '#FCE0D5'][
                                            ($contact->id ?? 0) % 6
                                        ];
                                    @endphp
                                    <tr data-contact-row data-contact-id="{{ $contact->id }}"
                                        data-groups="{{ $groupSlugs }}" data-group-ids="{{ $groupIdsCsv }}"
                                        data-source="{{ $contact->source ?? '' }}"
                                        data-search="{{ $searchKey }}">
                                        <td class="py-2 pl-4 pr-2 align-middle"><input class="row-check"
                                                type="checkbox" value="{{ $contact->id }}"></td>
                                        <td class="py-2 px-3 align-middle">
                                            <div class="flex items-center gap-2.5 min-w-0"><span
                                                    class="avatar w-8 h-8 rounded-full border border-ink-900/15 grid place-items-center font-extrabold text-[11px] text-ink-900 shrink-0"
                                                    style="background:{{ $avatarBg }}">{{ $initials }}</span>
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-[12.5px] truncate">
                                                        {{ $contact->name }}</div>
                                                    <div class="text-[11px] text-ink-500 truncate">
                                                        {{ $contact->language ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 align-middle">
                                            <div class="flex flex-wrap gap-1 max-w-full">
                                                @foreach ($contactGroupModels as $g)
                                                    <span
                                                        class="group-chip inline-flex items-center gap-[4px] px-1.5 py-[2px] rounded-full border border-paper-200 bg-paper-0 text-[10px] font-semibold whitespace-nowrap max-w-[140px] truncate"><span
                                                            class="group-dot w-[6px] h-[6px] rounded-full shrink-0"
                                                            style="background:{{ $g->color ?: 'var(--color-wa-deep)' }}"></span><span
                                                            class="truncate">{{ $g->user_group }}</span></span>
                                                @endforeach
                                            </div>
                                        </td>
                                        {{-- Tags — filled colour pills, deliberately distinct from the
                                             outlined group chips beside them so the two never read as
                                             one list. Clicking a tag filters the table to it. --}}
                                        <td class="py-2 px-3 align-middle" data-tag-cell="{{ $contact->id }}">
                                            <div class="flex flex-wrap gap-1 max-w-full">
                                                @forelse ($contact->tags as $t)
                                                    @php $tagColor = $t->color ?: 'var(--color-wa-deep)'; @endphp
                                                    <a href="{{ route('user.contacts', array_filter(['tag' => $t->id, 'q' => $searchQ ?: null, 'group' => $groupSel ?: null])) }}"
                                                        class="inline-flex items-center px-1.5 py-[2px] rounded-full text-[10px] font-semibold whitespace-nowrap max-w-[130px] truncate hover:opacity-80"
                                                        style="background:color-mix(in srgb, {{ $tagColor }} 10%, transparent);color:{{ $tagColor }};border:1px solid color-mix(in srgb, {{ $tagColor }} 25%, transparent)"
                                                        title="{{ __('Filter by :tag', ['tag' => $t->name]) }}">{{ $t->name }}</a>
                                                @empty
                                                    <span class="text-ink-500 text-[11px]">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 text-ink-700 text-[12px] truncate align-middle">
                                            {{ $contact->email }}</td>
                                        <td class="py-2 px-3 mono font-mono text-[11.5px] truncate align-middle">
                                            @php
                                                // Normalise to ONE display format for every row. Numbers are
                                                // stored inconsistently — some plain digits, some with +/spaces,
                                                // some with a separate country_code — which made mask_phone show
                                                // "xxxxxxxx0909" on one row and "+xx xx xxxx7417" on the next.
                                                // Strip to digits, prepend the country code only when it isn't
                                                // already inline, then prefix a single "+".
                                                $ccDigits   = preg_replace('/\D+/', '', (string) ($contact->country_code ?? ''));
                                                $numDigits  = preg_replace('/\D+/', '', (string) ($contact->mobile ?? ''));
                                                $fullDigits = ($ccDigits !== '' && !\Illuminate\Support\Str::startsWith($numDigits, $ccDigits))
                                                    ? $ccDigits . $numDigits
                                                    : $numDigits;
                                            @endphp
                                            {{ $fullDigits === '' ? '—' : '+' . mask_phone($fullDigits) }}</td>
                                        <td class="py-2 px-3 text-ink-500 text-[12px] truncate align-middle">
                                            {{ $contact->msg }}</td>
                                        <td class="py-2 pr-4 pl-3 align-middle">
                                            <div class="inline-flex items-center gap-1 whitespace-nowrap"><button
                                                    type="button" data-modal-open="editModal"
                                                    data-edit-contact="{{ $contact->id }}"
                                                    data-name="{{ $contact->name }}"
                                                    data-first-name="{{ $contact->first_name }}"
                                                    data-middle-name="{{ $contact->middle_name }}"
                                                    data-last-name="{{ $contact->last_name }}"
                                                    data-title="{{ $contact->title }}"
                                                    data-mobile="{{ $contact->mobile }}"
                                                    data-country-code="{{ $contact->country_code }}"
                                                    data-email="{{ $contact->email }}"
                                                    data-language="{{ $contact->language }}"
                                                    data-address="{{ $contact->address }}"
                                                    data-msg="{{ $contact->msg }}"
                                                    data-status="{{ is_array($contact->custom_attributes) ? $contact->custom_attributes['status'] ?? '' : '' }}"
                                                    data-source="{{ is_array($contact->custom_attributes) ? $contact->custom_attributes['source'] ?? '' : '' }}"
                                                    data-groups='@json($contactGroupIds)'
                                                    class="icon-btn w-7 h-7 rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50"
                                                    title="{{ __('Edit') }}"><svg viewBox="0 0 16 16"
                                                        class="w-3 h-3" fill="none" stroke="currentColor"
                                                        stroke-width="1.5">
                                                        <path d="M3 11.5V13h1.5L12 5.5 10.5 4 3 11.5z" />
                                                    </svg></button>
                                                <form method="POST"
                                                    action="{{ route('user.contacts.destroy', $contact->id) }}"
                                                    data-ajax="delete-contact" data-confirm="Delete this contact?"
                                                    class="inline-flex">@csrf @method('DELETE')<button type="submit"
                                                        class="icon-btn w-7 h-7 rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-accent-coral transition hover:border-accent-coral hover:bg-accent-coral/10"
                                                        title="{{ __('Delete') }}"><svg viewBox="0 0 16 16"
                                                            class="w-3 h-3" fill="none" stroke="currentColor"
                                                            stroke-width="1.5">
                                                            <path d="M3 4h10M6 4V2h4v2M5 6v7h6V6" />
                                                        </svg></button></form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-4">
                                            @include('user.partials.empty-state', [
                                                'message' =>
                                                    'No contacts match the current filters. Try clearing filters or add your first contact.',
                                                'resetHref' => url('/contacts'),
                                                'actionButtonAttrs' => 'data-modal-open="contactModal"',
                                                'actionLabel' => 'Add contact',
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="hidden p-4" data-list-grid-grid></div>
                    <div id="emptyState" class="hidden p-4">
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No contacts match the current filters. Clear search, choose another group, or add a new contact.',
                            'resetHref' => url('/contacts'),
                            'actionButtonAttrs' => 'data-modal-open="contactModal"',
                            'actionLabel' => 'Add contact',
                        ])
                    </div>
                </div>
                <div id="contactsPagination" data-panel="contacts">
                    @include('user.partials.pagination', [
                        'paginator' => $contacts,
                        'dataAttr' => 'data-contact-page',
                        'label' => 'contacts',
                    ])
                </div>

                <div id="groupsPanel" data-panel="groups" class="hidden grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($groups as $group)
                        @php
                            $groupColor = $group->color ?: 'var(--color-wa-deep)';
                            $groupCount = $groupCounts[$group->id] ?? 0;
                        @endphp
                        <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                            <div class="flex items-start gap-3">
                                <span class="w-11 h-11 rounded-xl grid place-items-center text-paper-0"
                                    style="background:{{ $groupColor }}"><svg viewBox="0 0 16 16" class="w-5 h-5"
                                        fill="none" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="6" cy="6" r="3" />
                                        <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5" />
                                    </svg></span>
                                <div class="flex-1 min-w-0">
                                    <h3
                                        class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight truncate">
                                        {{ $group->user_group }}</h3>
                                    <p class="text-[12px] text-ink-500 leading-relaxed">{{ $group->note }}</p>
                                </div>
                                <div class="text-right">
                                    <div
                                        class="serif font-serif font-normal tracking-[-0.01em] text-[30px] leading-none">
                                        {{ $groupCount }}</div>
                                    <div class="mono font-mono text-[10px] text-ink-500 uppercase">
                                        {{ __('contacts') }}</div>
                                </div>
                            </div>
                            <div class="mt-4 flex gap-2"><a
                                    href="{{ route('user.contacts', ['group' => $group->id]) }}"
                                    class="filter-tab inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 bg-paper-50 no-underline">View
                                    contacts</a><button type="button" data-modal-open="groupModal"
                                    data-edit-group="{{ $group->id }}" data-name="{{ $group->user_group }}"
                                    data-note="{{ $group->note }}" data-color="{{ $group->color }}"
                                    class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50"><svg
                                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.5">
                                        <path d="M3 11.5V13h1.5L12 5.5 10.5 4 3 11.5z" />
                                    </svg></button>
                                <form method="POST" action="{{ route('user.contact-groups.destroy', $group->id) }}"
                                    data-ajax="delete-group" data-group-id="{{ $group->id }}"
                                    data-confirm="Delete this group?" class="inline">@csrf @method('DELETE')<button
                                        type="submit"
                                        class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-accent-coral transition hover:border-accent-coral hover:bg-accent-coral/10"
                                        title="{{ __('Delete group') }}"><svg viewBox="0 0 16 16"
                                            class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            stroke-width="1.5">
                                            <path d="M3 4h10M6 4V2h4v2M5 6v7h6V6" />
                                        </svg></button></form>
                            </div>
                        </div>
                    @endforeach
                    <button data-modal-open="groupModal" data-new-group="1"
                        class="rounded-[14px] border border-dashed border-wa-deep bg-paper-0 hover:bg-wa-bubble/40 p-5 text-left transition-colors">
                        <span class="w-11 h-11 rounded-xl grid place-items-center bg-wa-bubble text-wa-deep"><svg
                                viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg></span>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[22px] mt-3">
                            {{ __('Create new group') }}</div>
                        <p class="text-[12px] text-ink-500">
                            {{ __('Bucket contacts by segment, funnel stage, or interest.') }}</p>
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-5">
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 01') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('What is a contact?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('A person or customer record with phone, email, notes, language, and group membership used across messages.') }}
                        </p>
                    </div>
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 02') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('How should I use groups?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('Create groups for segments like VIPs, new signups, cart abandoners, or local audiences before sending campaigns.') }}
                        </p>
                    </div>
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 03') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('How do I keep lists clean?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('Remove invalid numbers, respect unsubscribes, and import only opted-in contacts with a clear source.') }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <div id="contactModal"
        class="modal fixed inset-0 z-50 hidden items-center justify-center p-5 bg-[rgba(11,31,28,0.46)] [&.open]:flex">
        <form method="POST" action="{{ route('user.contacts.store') }}" id="addContactForm"
            data-ajax="add-contact" enctype="multipart/form-data"
            class="modal-panel w-full max-h-[90vh] overflow-hidden flex flex-col bg-white border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] max-w-3xl">
            @csrf
            <div
                class="px-5 py-4 bg-paper-0 hairline-b border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('New contact') }}</div>
                    <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                        {{ __('Create a contact') }}</h2>
                </div>
                <button type="button" data-modal-close
                    class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50"><svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg></button>
            </div>
            <div class="modal-body overflow-y-auto px-5 py-4 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Title') }}
                            <span class="req text-accent-coral">*</span></label><select name="title"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option>Mr</option>
                            <option>{{ __('Mrs') }}</option>
                            <option>{{ __('Miss') }}</option>
                            <option>Ms</option>
                            <option>Dr</option>
                        </select></div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('First name') }}
                            <span class="req text-accent-coral">*</span></label><input name="first_name"
                            value="{{ old('first_name') }}" required
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Enter first name') }}"></div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Middle name') }}</label><input
                            name="middle_name" value="{{ old('middle_name') }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Optional') }}"></div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Last name') }}</label><input
                            name="last_name" value="{{ old('last_name') }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Enter last name') }}"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Contact image') }}</label><label
                            class="file-tile flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble hover:border-solid [&.has-file]:border-solid [&.has-file]:bg-wa-bubble"><span
                                class="file-icon w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="8" cy="6" r="3" />
                                    <path d="M2 14c0-3 3-5 6-5s6 2 6 5" />
                                </svg></span>
                            <div class="file-meta flex-1 min-w-0">
                                <div
                                    class="file-title text-[12px] font-semibold text-ink-900 whitespace-nowrap overflow-hidden text-ellipsis">
                                    {{ __('Upload profile picture') }}</div>
                                <div class="file-sub text-[10.5px] text-ink-500 font-mono">
                                    {{ __('PNG or JPG / up to 2 MB') }}</div>
                            </div><span
                                class="file-action text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0 [&.danger]:text-accent-coral [&.danger]:border-accent-coral">{{ __('Browse') }}</span><input
                                type="file" name="image" accept="image/*" class="hidden">
                        </label></div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Language') }}</label><input
                            name="language" value="{{ old('language') }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('English') }}"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Mobile number') }}
                            <span class="req text-accent-coral">*</span></label>
                        <div class="wa-iti-wrap"><input type="tel" id="create-phone" name="mobile" value="{{ old('mobile') }}" required
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Enter mobile number') }}"></div>
                        <input type="hidden" name="country_code" id="create-cc" value="{{ old('country_code', app_default_country()['code']) }}"></div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Email') }}</label><input
                            name="email" value="{{ old('email') }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            type="email" placeholder="{{ __('Enter email') }}"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Memo') }}</label>
                        <textarea name="msg" rows="3" maxlength="1024"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-none"
                            placeholder="{{ __('Notes about this contact') }}">{{ old('msg') }}</textarea>
                    </div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Address') }}</label>
                        <textarea name="address"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Enter address') }}">{{ old('address') }}</textarea>
                    </div>
                </div>
                {{-- Status (VIP / Customer / …) + Source. The contacts table has no
                     status column — these are stored in custom_attributes, the same
                     mapping the CSV import's "apply to every row" knobs use, so a
                     single-added contact is segmented identically to an imported one. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Status') }}</label>
                        <select name="status"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">{{ __('— none —') }}</option>
                            <option value="active" @selected(old('status') === 'active')>{{ __('Active') }}</option>
                            <option value="prospect" @selected(old('status') === 'prospect')>{{ __('Prospect') }}</option>
                            <option value="customer" @selected(old('status') === 'customer')>{{ __('Customer') }}</option>
                            <option value="vip" @selected(old('status') === 'vip')>{{ __('VIP') }}</option>
                        </select>
                    </div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Source') }}</label>
                        <input type="text" name="source" maxlength="128" value="{{ old('source') }}"
                            placeholder="{{ __('e.g. Website form') }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                </div>
                <div><label
                        class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Contact groups') }}</label>
                    <div class="hairline border border-paper-200 rounded-lg p-2 flex flex-wrap gap-1.5 bg-paper-0">
                        @forelse ($groups as $group)
                            <label
                                class="group-chip inline-flex items-center gap-[5px] px-2 py-[3px] rounded-full border border-paper-200 bg-paper-0 text-[10.5px] font-semibold whitespace-nowrap cursor-pointer hover:bg-paper-50 has-[:checked]:bg-wa-bubble has-[:checked]:border-wa-deep"><input
                                    type="checkbox" name="contact_group[]" value="{{ $group->id }}"
                                    class="w-3 h-3 accent-wa-deep"><span
                                    class="group-dot w-[7px] h-[7px] rounded-full"
                                style="background:{{ $group->color ?: 'var(--color-wa-deep)' }}"></span>{{ $group->user_group }}</label>@empty<span
                                class="text-[12px] text-ink-500 px-2 py-1">{{ __('No groups yet — create one first.') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="px-5 py-4 bg-paper-0 hairline-t border-t border-paper-200 flex justify-end gap-2">
                <button type="button" data-modal-close
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save contact') }}</button>
            </div>
        </form>
    </div>

    <div id="editModal"
        class="modal fixed inset-0 z-50 hidden items-center justify-center p-5 bg-[rgba(11,31,28,0.46)] [&.open]:flex">
        <form method="POST" action="" id="editContactForm" data-ajax="edit-contact"
            data-update-template="{{ route('user.contacts.update', ':id') }}" enctype="multipart/form-data"
            class="modal-panel w-full max-h-[90vh] overflow-hidden flex flex-col bg-white border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] max-w-3xl">
            @csrf
            <input type="hidden" name="_method" value="PUT">
            <div
                class="px-5 py-4 bg-paper-0 hairline-b border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Edit contact') }}</div>
                    <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                        {{ __('Edit contact details') }}</h2>
                </div>
                <button type="button" data-modal-close
                    class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50"><svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg></button>
            </div>
            <div class="modal-body overflow-y-auto px-5 py-4 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Title') }}</label><select
                            name="title" data-edit-field="title"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option>Mr</option>
                            <option>{{ __('Mrs') }}</option>
                            <option>{{ __('Miss') }}</option>
                            <option>Ms</option>
                            <option>Dr</option>
                        </select></div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('First name') }}
                            <span class="req text-accent-coral">*</span></label><input name="first_name"
                            data-edit-field="first_name" required
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Middle name') }}</label><input
                            name="middle_name" data-edit-field="middle_name"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Last name') }}</label><input
                            name="last_name" data-edit-field="last_name"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Mobile number') }}
                            <span class="req text-accent-coral">*</span></label>
                        <div class="wa-iti-wrap"><input type="tel" id="edit-phone" name="mobile" data-edit-field="mobile" required
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                        <input type="hidden" name="country_code" id="edit-cc" data-edit-field="country_code"></div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Email') }}</label><input
                            name="email" data-edit-field="email" type="email"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Memo') }}</label>
                        <textarea name="msg" data-edit-field="msg" rows="3" maxlength="1024"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-none"
                            placeholder="{{ __('Notes about this contact') }}"></textarea>
                    </div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Address') }}</label>
                        <textarea name="address" data-edit-field="address"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></textarea>
                    </div>
                </div>
                {{-- Status / Source — same custom_attributes pair the add modal and
                     the CSV import write. Prefilled by bindEditButton from the row's
                     data-status / data-source. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Status') }}</label>
                        <select name="status" data-edit-field="status"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">{{ __('— none —') }}</option>
                            <option value="active">{{ __('Active') }}</option>
                            <option value="prospect">{{ __('Prospect') }}</option>
                            <option value="customer">{{ __('Customer') }}</option>
                            <option value="vip">{{ __('VIP') }}</option>
                        </select>
                    </div>
                    <div><label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Source') }}</label>
                        <input type="text" name="source" data-edit-field="source" maxlength="128"
                            placeholder="{{ __('e.g. Website form') }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                </div>
                <div><label
                        class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Contact groups') }}</label>
                    <div class="hairline border border-paper-200 rounded-lg p-2 flex flex-wrap gap-1.5 bg-paper-0"
                        data-edit-groups>
                        @forelse ($groups as $group)
                            <label
                                class="group-chip inline-flex items-center gap-[5px] px-2 py-[3px] rounded-full border border-paper-200 bg-paper-0 text-[10.5px] font-semibold whitespace-nowrap cursor-pointer hover:bg-paper-50 has-[:checked]:bg-wa-bubble has-[:checked]:border-wa-deep"><input
                                    type="checkbox" name="contact_group[]" value="{{ $group->id }}"
                                    data-edit-group-input="{{ $group->id }}" class="w-3 h-3 accent-wa-deep"><span
                                    class="group-dot w-[7px] h-[7px] rounded-full"
                                style="background:{{ $group->color ?: 'var(--color-wa-deep)' }}"></span>{{ $group->user_group }}</label>@empty<span
                                class="text-[12px] text-ink-500 px-2 py-1">{{ __('No groups yet.') }}</span>
                        @endforelse
                    </div>
                </div>
                {{-- Tags — adding one fires the flow "Audience: when a tag is added" trigger. --}}
                <div data-contact-tags-block>
                    <label class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Tags') }}</label>
                    <div class="hairline border border-paper-200 rounded-lg p-2 flex flex-wrap gap-1.5 bg-paper-0 min-h-[38px]" data-edit-tags>
                        <span class="text-[12px] text-ink-500 px-2 py-1" data-tags-empty>{{ __('No tags yet.') }}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1.5">
                        {{-- Multi-select picker: every workspace tag as a toggle
 chip. Filled by user-contacts-index.js from the same
 GET /contacts/{id}/tags payload that renders the chips
 above, so attached state and the picker can't disagree. --}}
                    </div>
                    <div data-tag-picker class="flex flex-wrap gap-1.5 mt-2"></div>
                    <div class="flex gap-2 mt-2">
                        <input list="contactTagList" data-tag-input autocomplete="off"
                            placeholder="{{ __('Add or pick a tag…') }}"
                            class="ctrl flex-1 px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        <datalist id="contactTagList"></datalist>
                        <button type="button" data-tag-add
                            class="px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold whitespace-nowrap">{{ __('Add tag') }}</button>
                    </div>
                    <p class="text-[10.5px] text-ink-500 mt-1">{{ __('Adding a tag can auto-enroll this contact into a flow (Audience: when a tag is added).') }}</p>
                </div>
            </div>
            <div class="px-5 py-4 bg-paper-0 hairline-t border-t border-paper-200 flex justify-end gap-2">
                <button type="button" data-modal-close
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save changes') }}</button>
            </div>
        </form>
    </div>

    <div id="bulkModal"
        class="modal fixed inset-0 z-50 hidden items-center justify-center p-5 bg-[rgba(11,31,28,0.46)] [&.open]:flex">
        <form method="POST" action="{{ route('user.contacts.import') }}" enctype="multipart/form-data"
            id="bulkUploadForm" data-ajax="bulk-upload"
            class="modal-panel w-full max-h-[90vh] overflow-hidden flex flex-col bg-white border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] max-w-xl">
            @csrf
            <div
                class="px-5 py-4 bg-paper-0 hairline-b border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Bulk import') }}</div>
                    <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                        {{ __('Upload contacts from a file') }}</h2>
                </div>
                <button type="button" data-modal-close
                    class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50"><svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg></button>
            </div>
            <div class="modal-body overflow-y-auto px-5 py-4 space-y-4">
                <div><label
                        class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('File') }}
                        <span class="req text-accent-coral">*</span></label><label
                        class="file-tile flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble hover:border-solid [&.has-file]:border-solid [&.has-file]:bg-wa-bubble"><span
                            class="file-icon w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0"><svg
                                viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M8 3v8M5 6l3-3 3 3M3 13h10" />
                            </svg></span>
                        <div class="file-meta flex-1 min-w-0">
                            <div class="file-title text-[12px] font-semibold text-ink-900 whitespace-nowrap overflow-hidden text-ellipsis"
                                data-bulk-file-label>{{ __('Choose CSV file') }}</div>
                            <div class="file-sub text-[10.5px] text-ink-500 font-mono">{{ __('CSV / max 5 MB') }}
                            </div>
                        </div><span
                            class="file-action text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0 [&.danger]:text-accent-coral [&.danger]:border-accent-coral">{{ __('Browse') }}</span><input
                            type="file" name="file" accept=".csv,.txt" required class="hidden"
                            onchange="document.querySelector('[data-bulk-file-label]').textContent=this.files[0]?.name||'Choose CSV file'">
                    </label></div>
                {{-- Smart-column hint instead of strict-format rules. The
 controller auto-detects "Phone Number", "telefono", "first_name", etc. --}}
                <div class="hairline border border-paper-200 rounded-lg overflow-hidden">
                    <div
                        class="px-3 py-2 bg-paper-50 mono font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        {{ __('Smart column detection') }}</div>
                    <div class="px-3 py-2.5 text-[11.5px] text-ink-600">
                        Your CSV needs a header row. We auto-match common names: <b>name</b>, <b>phone / mobile /
                            whatsapp</b>, <b>email</b>, <b>group</b>, <b>country_code</b>, <b>language</b>. Anything we
                        can't recognize is ignored — no special format needed.
                    </div>
                    <div class="px-3 py-2 border-t border-paper-200 bg-paper-0">
                        <a href="{{ route('user.contacts.sample-csv') }}"
                            class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-wa-deep hover:text-wa-teal">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M8 2v8M5 7l3 3 3-3M3 13h10" />
                            </svg>
                            {{ __('Download sample CSV') }}
                        </a>
                    </div>
                </div>

                {{-- #25-30 — "Apply to ALL imported rows" knobs. Save the operator
 from per-row Excel surgery. --}}
                <div class="hairline border border-paper-200 rounded-lg overflow-hidden">
                    <div
                        class="px-3 py-2 bg-paper-50 mono font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        {{ __('Apply to every imported contact') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 text-[12px]">
                        <label class="block">
                            <span
                                class="text-[11px] font-semibold text-ink-700 block mb-1">{{ __('Status') }}</span>
                            <select name="status"
                                class="w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 focus:outline-none focus:border-wa-deep">
                                <option value="">— none —</option>
                                <option value="active">{{ __('Active') }}</option>
                                <option value="prospect">{{ __('Prospect') }}</option>
                                <option value="customer">{{ __('Customer') }}</option>
                                <option value="vip">{{ __('VIP') }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span
                                class="text-[11px] font-semibold text-ink-700 block mb-1">{{ __('Source') }}</span>
                            <input type="text" name="source" maxlength="128"
                                placeholder="{{ __('e.g. Spring 2026 campaign') }}"
                                class="w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 focus:outline-none focus:border-wa-deep">
                        </label>

                        <label class="block col-span-2">
                            <span
                                class="text-[11px] font-semibold text-ink-700 block mb-1">{{ __('Group') }}</span>
                            <select name="group_id"
                                class="w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-paper-0 focus:outline-none focus:border-wa-deep">
                                <option value="">— none —</option>
                                @php
                                    $myGroups = \App\Models\ContactGroup::query()
                                        ->forCurrentWorkspace()
                                        ->orderBy('id', 'desc')
                                        ->limit(200)
                                        ->get(['id', 'user_group']);
                                @endphp
                                @foreach ($myGroups as $g)
                                    <option value="{{ $g->id }}">{{ $g->user_group }}</option>
                                @endforeach
                            </select>
                            <span
                                class="text-[10.5px] text-ink-500 mt-1 block">{{ __('All imported contacts get added to this group. Per-row CSV groups also apply.') }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="px-5 py-4 bg-paper-0 hairline-t border-t border-paper-200 flex justify-end gap-2">
                <button type="button" data-modal-close
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Upload') }}</button>
            </div>
        </form>
    </div>

    <div id="groupModal"
        class="modal fixed inset-0 z-50 hidden items-center justify-center p-5 bg-[rgba(11,31,28,0.46)] [&.open]:flex">
        <form method="POST" action="{{ route('user.contact-groups.store') }}" id="groupForm"
            data-ajax="group-save" data-create-action="{{ route('user.contact-groups.store') }}"
            data-update-template="{{ route('user.contact-groups.update', ':id') }}"
            class="modal-panel w-full max-h-[90vh] overflow-hidden flex flex-col bg-white border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] max-w-xl">
            @csrf
            <input type="hidden" name="_method" id="groupFormMethod" value="">
            <div
                class="px-5 py-4 bg-paper-0 hairline-b border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Contact group') }}</div>
                    <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                        {{ __('Create or edit group') }}</h2>
                </div>
                <button type="button" data-modal-close
                    class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50"><svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg></button>
            </div>
            <div class="modal-body overflow-y-auto px-5 py-4 space-y-4">
                <div><label
                        class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Group name') }}
                        <span class="req text-accent-coral">*</span></label><input name="name"
                        data-group-field="name" value="{{ old('name') }}" required
                        class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="{{ __('Eg. My friends group') }}"></div>
                <div><label
                        class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Note') }}</label>
                    <textarea name="note" data-group-field="note" rows="3" maxlength="1024"
                        class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-none"
                        placeholder="{{ __('Describe this group') }}">{{ old('note') }}</textarea>
                </div>
                <div><label
                        class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Accent color') }}</label>
                    <div class="flex gap-2">
                        <label class="cursor-pointer"><input type="radio" name="color" value="#7C3AED"
                                data-group-field="color" class="hidden peer" checked><span
                                class="block w-8 h-8 rounded-full bg-[#7C3AED] peer-checked:ring-2 peer-checked:ring-ink-900 peer-checked:ring-offset-2"></span></label>
                        <label class="cursor-pointer"><input type="radio" name="color" value="#22C55E"
                                data-group-field="color" class="hidden peer"><span
                                class="block w-8 h-8 rounded-full bg-[#22C55E] peer-checked:ring-2 peer-checked:ring-ink-900 peer-checked:ring-offset-2"></span></label>
                        <label class="cursor-pointer"><input type="radio" name="color" value="#E87A5D"
                                data-group-field="color" class="hidden peer"><span
                                class="block w-8 h-8 rounded-full bg-[#E87A5D] peer-checked:ring-2 peer-checked:ring-ink-900 peer-checked:ring-offset-2"></span></label>
                        <label class="cursor-pointer"><input type="radio" name="color" value="#E5A04E"
                                data-group-field="color" class="hidden peer"><span
                                class="block w-8 h-8 rounded-full bg-[#E5A04E] peer-checked:ring-2 peer-checked:ring-ink-900 peer-checked:ring-offset-2"></span></label>
                        <label class="cursor-pointer"><input type="radio" name="color" value="#13478A"
                                data-group-field="color" class="hidden peer"><span
                                class="block w-8 h-8 rounded-full bg-[#13478A] peer-checked:ring-2 peer-checked:ring-ink-900 peer-checked:ring-offset-2"></span></label>
                    </div>
                </div>
            </div>
            <div class="px-5 py-4 bg-paper-0 hairline-t border-t border-paper-200 flex justify-end gap-2">
                <button type="button" data-modal-close
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save group') }}</button>
            </div>
        </form>
    </div>

    <div id="deleteModal"
        class="modal fixed inset-0 z-50 hidden items-center justify-center p-5 bg-[rgba(11,31,28,0.46)] [&.open]:flex">
        <form method="POST" action="{{ route('user.contacts.bulk-delete') }}" id="bulkDeleteForm"
            data-ajax="bulk-delete"
            class="modal-panel w-full max-h-[90vh] overflow-hidden flex flex-col bg-white border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] max-w-md">
            @csrf
            <div
                class="px-5 py-4 bg-paper-0 hairline-b border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-accent-coral">
                        {{ __('Confirm') }}</div>
                    <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">
                        {{ __('Delete selected contacts?') }}</h2>
                </div>
                <button type="button" data-modal-close
                    class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50"><svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg></button>
            </div>
            <div class="px-5 py-4 text-[13px] text-ink-600">
                {{ __('This removes the selected contacts from all lists and groups. This action cannot be undone.') }}
                <div id="bulkDeleteIds"></div>
            </div>
            <div class="px-5 py-4 bg-paper-0 hairline-t border-t border-paper-200 flex justify-end gap-2">
                <button type="button" data-modal-close
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Keep contacts') }}</button>
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-accent-coral text-paper-0 text-[12px] font-semibold">{{ __('Delete') }}</button>
            </div>
        </form>
    </div>

</x-layouts.user>

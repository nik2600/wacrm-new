<x-layouts.admin :title="__('Team inbox')" admin-key="team-inbox" page="admin-team-inbox-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Team inbox') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Platform · Cross-workspace inbox') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Team') }}
                    <span class="italic text-wa-deep">{{ __('inbox') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Read-only view across every customer workspace. Use it to triage SLA breaches, flag spam, and (with reason) impersonate workspace owners for support cases.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/team-inbox#audit') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Audit log') }}</a>
            </div>
        </div>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" id="adm-stats">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Workspaces') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2" data-stat="workspaces">—</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('total active') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('SLA breached') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-coral" data-stat="breach_total">—</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('across platform') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Open conversations') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber" data-stat="open_total">—</div>
                <div class="text-[11px] text-accent-amber mt-2">{{ __('all workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active impersonation') }}</div>
                <div class="font-serif text-[20px] leading-none mt-2" data-stat="impersonation">{{ __('none') }}
                </div>
                <div class="text-[11px] text-ink-500 mt-2" data-stat="impersonation-sub">{{ __('no active session') }}
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            <div class="lg:col-span-2 space-y-4 min-w-0">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-paper-200 flex items-center gap-3 flex-wrap">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Conversations') }}</div>
                        <div class="flex items-center gap-1" id="adm-tabs">
                            <button data-adm-tab="sla_breach" class="ti-tab active">{{ __('SLA breach') }}</button>
                            <button data-adm-tab="unassigned" class="ti-tab">{{ __('Unassigned') }}</button>
                            <button data-adm-tab="spam" class="ti-tab">{{ __('Spam') }}</button>
                            <button data-adm-tab="all" class="ti-tab">{{ __('All') }}</button>
                        </div>
                        <select id="adm-workspace-filter"
                            class="ml-auto px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[11.5px] focus:outline-none focus:border-wa-deep">
                            <option value="">{{ __('All workspaces') }}</option>
                        </select>
                    </div>
                    <div id="adm-conv-list" class="max-h-[60vh] overflow-y-auto"></div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Workspaces') }}</div>
                        <h2 class="font-serif text-[18px] leading-tight mt-1">{{ __('Recent activity') }}</h2>
                    </div>
                    <div id="adm-workspace-list" class="max-h-[55vh] overflow-y-auto"></div>
                </div>
            </div>

        </section>

        <section id="audit" class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-3 border-b border-paper-200">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Audit log') }}</div>
                <h2 class="font-serif text-[20px] leading-tight mt-1">{{ __('Platform actions') }}</h2>
            </div>
            <div id="adm-audit-list" class="max-h-[40vh] overflow-y-auto"></div>
        </section>

    </main>

    <!-- Conversation drawer -->
    <div id="adm-drawer" class="hidden fixed inset-0 z-50">
        <div class="absolute inset-0 bg-ink-900/40" data-close-drawer></div>
        <aside class="absolute right-0 top-0 bottom-0 w-full max-w-[640px] bg-paper-0 shadow-2xl flex flex-col">
            <header class="px-5 py-4 border-b border-paper-200 flex items-center gap-3">
                <button data-close-drawer
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center">×</button>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] text-ink-500 uppercase tracking-wider" id="adm-drawer-workspace">
                    </div>
                    <div class="font-serif text-[18px] truncate" id="adm-drawer-title">{{ __('Conversation') }}</div>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <button id="adm-impersonate-btn"
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal">{{ __('Impersonate') }}</button>
                    <button id="adm-spam-btn"
                        class="px-3 py-1.5 rounded-full border border-accent-coral/40 text-accent-coral text-[11.5px] font-semibold hover:bg-accent-coral/10">{{ __('Mark spam') }}</button>
                </div>
            </header>
            <div class="flex-1 overflow-y-auto">
                <div class="px-5 py-3 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-2">{{ __('Thread') }}
                    </div>
                    <div id="adm-drawer-thread" class="space-y-2"></div>
                </div>
                <div class="px-5 py-3 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-2">
                        {{ __('Platform notes') }}</div>
                    <div id="adm-drawer-platform-notes" class="space-y-2 mb-3"></div>
                    <textarea id="adm-platform-note" rows="2" placeholder="{{ __('Write a platform-only note…') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep"></textarea>
                    <div class="flex items-center gap-2 mt-2">
                        <select id="adm-platform-note-severity"
                            class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[11.5px]">
                            <option value="info">{{ __('Info') }}</option>
                            <option value="warn">{{ __('Warn') }}</option>
                            <option value="critical">{{ __('Critical') }}</option>
                        </select>
                        <button id="adm-platform-note-save"
                            class="ml-auto px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal">{{ __('Add note') }}</button>
                    </div>
                </div>
                <div class="px-5 py-3 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-2">
                        {{ __('Workspace notes') }}</div>
                    <div id="adm-drawer-notes" class="space-y-2"></div>
                </div>
                <div class="px-5 py-3">
                    <div class="font-mono text-[10px] uppercase tracking-wider text-ink-500 mb-2">{{ __('Events') }}
                    </div>
                    <div id="adm-drawer-events" class="space-y-1.5 text-[11.5px] text-ink-700"></div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Impersonation modal -->
    <div id="adm-impersonate-modal" class="hidden fixed inset-0 z-50 grid place-items-center">
        <div class="absolute inset-0 bg-ink-900/40"></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-md p-5 shadow-2xl">
            <div class="font-serif text-[20px] mb-1">{{ __('Start impersonation') }}</div>
            <p class="text-[12px] text-ink-500 mb-3">
                {{ __("You'll be viewing the customer's workspace as if you were their owner. The session is audit-logged.") }}
            </p>
            <textarea id="adm-impersonate-reason" rows="3" placeholder="{{ __('Reason (required, min 8 chars)…') }}"
                class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep"></textarea>
            <div class="flex items-center gap-2 mt-3">
                <button id="adm-impersonate-cancel"
                    class="ml-auto px-3 py-1.5 rounded-full border border-paper-200 text-[12px]">{{ __('Cancel') }}</button>
                <button id="adm-impersonate-confirm"
                    class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold">{{ __('Start session') }}</button>
            </div>
        </div>
    </div>

    <div id="toast"></div>

</x-layouts.admin>

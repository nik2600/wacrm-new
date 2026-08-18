<x-layouts.user :title="__('Team members')" nav-key="team-inbox" page="user-team-inbox-members">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7" data-members-state data-members-search="">

        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 mb-3">
            <a href="{{ url('/team-inbox') }}"
                class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Team inbox') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Members') }}</span>
        </div>

        <div class="flex items-end justify-between gap-4 mb-6 flex-wrap">
            <div class="min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Workspace') }}
                </div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Team') }}
                    <span class="italic text-wa-deep">{{ __('members') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Everyone with access to') }} <span
                        class="font-semibold">{{ optional(auth()->user()?->currentWorkspace)->name }}</span>. Roles
                    control what each person can see and do in the team inbox.</p>
            </div>
            @canWorkspace('member.invite')
            <button id="invite-btn"
                class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5 shrink-0">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3v10M3 8h10" />
                </svg>
                Invite teammate
            </button>
            @endcanWorkspace
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            {{-- ===== SIDEBAR — role filters & quick stats ===== --}}
            <aside class="space-y-3">
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Filter by role') }}</div>
                    @foreach ([
        'all' => ['label' => 'All members', 'dot' => null],
        'owner' => ['label' => 'Owners', 'dot' => 'bg-wa-deep'],
        'admin' => ['label' => 'Admins', 'dot' => 'bg-[#13478A]'],
        'manager' => ['label' => 'Managers', 'dot' => 'bg-[#5B3D8A]'],
        'agent' => ['label' => 'Agents', 'dot' => 'bg-accent-amber'],
        'viewer' => ['label' => 'Viewers', 'dot' => 'bg-paper-200'],
    ] as $key => $row)
                        <button type="button" data-role-filter="{{ $key }}"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $key === 'all' ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span class="flex items-center gap-2">
                                @if ($row['dot'])
                                    <span class="w-2 h-2 rounded-full {{ $row['dot'] }}"></span>
                                @endif
                                {{ $row['label'] }}
                            </span>
                            <span data-role-count="{{ $key }}"
                                class="font-mono text-[11px] {{ $key === 'all' ? 'opacity-90' : 'text-ink-500' }}">0</span>
                        </button>
                    @endforeach
                </div>

                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v3l2 2" />
                        </svg>
                        How roles work
                    </div>
                    <div class="space-y-1.5">
                        <div><span class="font-semibold">{{ __('Owner') }}</span> — full access including billing.
                        </div>
                        <div><span class="font-semibold">{{ __('Admin') }}</span> — workspace settings, can invite.
                        </div>
                        <div><span class="font-semibold">{{ __('Manager') }}</span> — see all queues, assign, resolve.
                        </div>
                        <div><span class="font-semibold">{{ __('Agent') }}</span> — handle assigned conversations.
                        </div>
                        <div><span class="font-semibold">{{ __('Viewer') }}</span> — read-only.</div>
                    </div>
                </div>
            </aside>

            {{-- ===== MAIN — search bar + member table ===== --}}
            <section class="space-y-4">

                <div class="border border-paper-200 rounded-2xl bg-paper-0 shadow-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-paper-200 flex items-center gap-3 flex-wrap">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            <span data-member-count>0</span> members
                        </div>
                        <div class="ml-auto relative">
                            <svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input id="member-search" type="search" placeholder="{{ __('Search by name or email…') }}"
                                class="pl-9 pr-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12px] focus:outline-none focus:bg-paper-0 focus:border-wa-deep w-72" />
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                                <tr>
                                    <th class="text-left px-5 py-2.5 font-medium">{{ __('Name') }}</th>
                                    <th class="text-left px-3 py-2.5 font-medium w-[180px]">{{ __('Role') }}</th>
                                    <th class="text-left px-3 py-2.5 font-medium w-[120px]">{{ __('Status') }}</th>
                                    <th class="text-left px-3 py-2.5 font-medium w-[140px]">{{ __('Joined') }}</th>
                                    <th class="text-right pl-3 pr-5 py-2.5 font-medium w-[60px]"></th>
                                </tr>
                            </thead>
                            <tbody id="members-tbody" class="divide-y divide-paper-200">
                                @for ($i = 0; $i < 5; $i++)
                                    <tr>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="skeleton w-9 h-9 rounded-full shrink-0"></div>
                                                <div class="flex-1 space-y-1.5">
                                                    <div class="skeleton h-3 rounded w-1/3"></div>
                                                    <div class="skeleton h-2.5 rounded w-1/2"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="skeleton h-3 rounded w-16"></div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="skeleton h-3 rounded w-12"></div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="skeleton h-3 rounded w-20"></div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="skeleton h-3 rounded w-8"></div>
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
        </div>
    </main>

    {{-- ====== Invite teammate modal — same pattern as /team-inbox ===== --}}
    <div id="invite-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-invite></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-md p-5 shadow-2xl">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Workspace') }}
                    </div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Invite a teammate') }}</h3>
                </div>
                <button data-close-invite
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <p class="text-[12px] text-ink-500 mt-1">
                {{ __("We'll email them an invite. If email isn't deliverable we'll show a one-time password you can share manually.") }}
            </p>
            <form id="invite-form" class="mt-4 space-y-3">
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Full name') }}</label>
                    <input type="text" name="name" required maxlength="191"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                        placeholder="{{ __('e.g. Riya Shah') }}" />
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Email') }}</label>
                    <input type="email" name="email" required maxlength="191"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                        placeholder="riya@company.com" />
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Workspace role') }}</label>
                    <select name="role" required
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                        <option value="agent">{{ __('Agent — handles assigned conversations only') }}</option>
                        <option value="manager">{{ __('Manager — sees all teams, can assign + resolve') }}</option>
                        <option value="admin">{{ __('Admin — full workspace access (no billing)') }}</option>
                        <option value="viewer">{{ __('Viewer — read-only') }}</option>
                        @canWorkspace('member.role.assign')
                        <option value="owner">{{ __('Owner — everything including billing') }}</option>
                        @endcanWorkspace
                    </select>
                </div>
                <div id="invite-result" class="hidden rounded-lg p-3 text-[12px]"></div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" data-close-invite
                        class="ml-auto px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                    <button type="submit" id="invite-submit"
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Send invite') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast"></div>

</x-layouts.user>

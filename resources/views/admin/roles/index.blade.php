<x-layouts.admin :title="__('Admin · Roles')" admin-key="roles" page="roles-index">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Roles') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <!-- Heading -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Users & access · Roles') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Roles &') }}
                    <span class="italic text-wa-deep">{{ __('permissions') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Define what each role can do across the platform. Each user is assigned one role; permissions are bundled per role.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/permissions') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="2" y="6" width="12" height="8" rx="1.5" />
                        <path d="M5 6V4a3 3 0 0 1 6 0v2" />
                    </svg>
                    Manage permissions
                </a>
                <a href="{{ url('/admin/roles/create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add role
                </a>
            </div>
        </div>

        @if (session('status'))
            <div
                class="rounded-2xl border border-wa-green/30 bg-wa-bubble/40 px-4 py-3 text-[12.5px] text-wa-deep flex items-start gap-2">
                <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                    stroke-width="1.7">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                <div>{{ session('status') }}</div>
            </div>
        @endif

        @if ($errors->any())
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12.5px] text-accent-coral">
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        @php
            $totalPermissions = \Spatie\Permission\Models\Permission::count();
            $totalRoles = $roles->count();
            $systemRoleNames = ['Super Admin', 'Admin'];
            $systemCount = $roles->whereIn('name', $systemRoleNames)->count();
            $customCount = $totalRoles - $systemCount;
            $rolesWithoutUsers = $roles->where('users_count', 0)->count();
        @endphp

        <!-- KPI strip -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total roles') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $totalRoles }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $systemCount }} system · {{ $customCount }}
                    {{ __('custom') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total permissions') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $totalPermissions }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across modules') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Users with role') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $roles->sum('users_count') }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across all roles') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Roles without users') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 text-accent-amber">{{ $rolesWithoutUsers }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('consider archiving') }}</div>
            </div>
        </section>

        <!-- Filter bar -->
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex flex-wrap items-center gap-1 shadow-card">
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0 active">
                {{ __('All') }} <span class="font-mono text-[11px] opacity-80">{{ $totalRoles }}</span></div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('System') }}</div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Custom') }}</div>
            <div class="flex-1"></div>
            <div class="relative">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                    fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="7" cy="7" r="5" />
                    <path d="m11 11 3 3" />
                </svg>
                <input placeholder="{{ __('Search roles…') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-72 focus:outline-none focus:border-wa-deep" />
            </div>
        </div>

        <!-- Roles table -->
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-2.5 w-[44px]">#</th>
                        <th class="text-left px-3 py-2.5">{{ __('Role name') }}</th>
                        <th class="text-left px-3 py-2.5 w-[110px]">{{ __('Type') }}</th>
                        <th class="text-left px-3 py-2.5 w-[110px]">{{ __('Users') }}</th>
                        <th class="text-left px-3 py-2.5 w-[140px]">{{ __('Permissions') }}</th>
                        <th class="text-left px-3 py-2.5 w-[140px]">{{ __('Last edited') }}</th>
                        <th class="text-center px-3 py-2.5 w-[60px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($roles as $idx => $role)
                        @php
                            $isSystem = in_array($role->name, $systemRoleNames, true);
                            $permRatio = $totalPermissions > 0 ? $role->permissions_count / $totalPermissions : 0;
                            $widthPct = (int) round($permRatio * 100);
                        @endphp
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 text-ink-500 font-mono">{{ $idx + 1 }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="w-8 h-8 rounded-lg bg-wa-deep text-paper-0 grid place-items-center"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                            stroke-width="1.7">
                                            <path d="M8 2l5 2v4c0 3.2-2 5.4-5 6-3-.6-5-2.8-5-6V4z" />
                                        </svg></span>
                                    <div>
                                        <div class="font-semibold leading-tight">{{ $role->name }}</div>
                                        <div class="text-[10.5px] text-ink-500 mt-0.5">
                                            {{ $isSystem ? 'System role' : 'Custom role' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                @if ($isSystem)
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-ink-900 text-paper-0 text-[10px] font-semibold">{{ __('System') }}</span>
                                @else
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-semibold">{{ __('Custom') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-mono text-[11.5px]">{{ $role->users_count }}</td>
                            <td class="px-3 py-3">
                                <span class="font-mono text-[11.5px] text-wa-deep">{{ $role->permissions_count }} /
                                    {{ $totalPermissions }}</span>
                                <div class="h-1 bg-paper-100 rounded-full mt-1 overflow-hidden">
                                    <div class="h-full bg-wa-deep" style="width: {{ $widthPct }}%"></div>
                                </div>
                            </td>
                            <td class="px-3 py-3 font-mono text-[10.5px] text-ink-500">
                                {{ optional($role->updated_at)->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-3 py-3 text-center">
                                <div class="relative inline-block">
                                    <button type="button"
                                        class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                        onclick="toggleRoleMenu(event,this)">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600"
                                            fill="currentColor">
                                            <circle cx="3" cy="8" r="1.2" />
                                            <circle cx="8" cy="8" r="1.2" />
                                            <circle cx="13" cy="8" r="1.2" />
                                        </svg>
                                    </button>
                                    <div
                                        class="role-action-menu hidden absolute right-0 top-full mt-1 z-50 w-[180px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                        <a href="{{ route('admin.roles.edit', $role->id) }}"
                                            class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                            </svg>{{ __('Edit role') }}
                                        </a>
                                        @if ($role->name !== 'Super Admin')
                                            <div class="border-t border-paper-200 my-1"></div>
                                            <form method="POST"
                                                action="{{ route('admin.roles.destroy', $role->id) }}"
                                                data-confirm="Delete role {{ addslashes($role->name) }}? Users assigned to this role will lose all its permissions. This cannot be undone."
                                                data-confirm-title="{{ __('Delete role') }}"
                                                data-confirm-text="Yes, delete" data-danger="1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10">
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.6">
                                                        <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                                    </svg>{{ __('Delete') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-[12.5px] text-ink-500">
                                No roles defined yet. <a href="{{ route('admin.roles.create') }}"
                                    class="text-wa-deep font-semibold hover:underline">{{ __('Create the first role') }}</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 rounded-b-2xl flex items-center justify-between">
                <div class="text-[11px] font-mono text-ink-500">Showing {{ $totalRoles }} of {{ $totalRoles }}
                    {{ __('roles') }}</div>
                <a href="{{ route('admin.roles.create') }}"
                    class="text-[12px] font-semibold text-wa-deep hover:underline inline-flex items-center gap-1">+ Add
                    role</a>
            </div>
        </div>
    </main>

    @push('scripts')
        <script>
            // Toggle the row action menu on click and dismiss on outside click.
            function toggleRoleMenu(e, btn) {
                e.stopPropagation();
                document.querySelectorAll('.role-action-menu').forEach(m => {
                    if (!btn.parentElement.contains(m)) m.classList.add('hidden');
                });
                const menu = btn.parentElement.querySelector('.role-action-menu');
                if (menu) menu.classList.toggle('hidden');
            }
            document.addEventListener('click', () => {
                document.querySelectorAll('.role-action-menu').forEach(m => m.classList.add('hidden'));
            });
        </script>
    @endpush

</x-layouts.admin>

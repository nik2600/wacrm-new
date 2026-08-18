<x-layouts.admin :title="__('Admin · Permissions')" admin-key="permissions">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Permissions') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <!-- Heading -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Users & access · Permissions') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('All') }}
                    <span class="italic text-wa-deep">{{ __('permissions') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Every permission flag the platform recognises, grouped by module. Add a new flag here, then attach it to roles in the role editor.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/roles') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M8 2l5 2v4c0 3.2-2 5.4-5 6-3-.6-5-2.8-5-6V4z" />
                    </svg>
                    Back to roles
                </a>
                <a href="{{ url('/admin/permissions/create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add permission
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-wa-green/30 bg-wa-bubble/40 px-4 py-3 text-[12.5px] text-wa-deep">
                {{ session('status') }}</div>
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
            $totalPermissions = $permissions->count();
            $moduleCount = $permissionsGrouped->count();
            $rolesCount = \Spatie\Permission\Models\Role::count();
            // Permissions used in at least one role.
            $usedPermissionIds = \Illuminate\Support\Facades\DB::table('role_has_permissions')
                ->select('permission_id')
                ->distinct()
                ->pluck('permission_id');
            $usedCount = $usedPermissionIds->count();
            $orphanCount = $totalPermissions - $usedCount;
            // Per-permission role counts (for the listing cards).
            $rolesPerPerm = \Illuminate\Support\Facades\DB::table('role_has_permissions')
                ->select('permission_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as roles_count'))
                ->groupBy('permission_id')
                ->pluck('roles_count', 'permission_id');
            $lastPerm = $permissions->sortByDesc('created_at')->first();
        @endphp

        <!-- KPI strip -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total permissions') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $totalPermissions }}</div>
                <div class="text-[11px] text-ink-500 mt-2">across {{ $moduleCount }} {{ __('modules') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Used in roles') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $usedCount }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('attached to ≥ 1 role') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Orphan permissions') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 text-accent-amber">{{ $orphanCount }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('attached to 0 roles') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Last added') }}</div>
                <div class="font-serif text-[24px] leading-none mt-2 truncate">{{ $lastPerm?->name ?? '—' }}</div>
                <div class="text-[11px] text-ink-500 mt-2">
                    {{ $lastPerm ? optional($lastPerm->created_at)->format('Y-m-d') : '' }}</div>
            </div>
        </section>

        <!-- Search bar -->
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex flex-wrap items-center gap-2 shadow-card">
            <div class="relative flex-1 max-w-[420px]">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                    fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="7" cy="7" r="5" />
                    <path d="m11 11 3 3" />
                </svg>
                <input placeholder="{{ __('Search permission name…') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full focus:outline-none focus:border-wa-deep" />
            </div>
            <select
                class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                <option>{{ __('All modules') }}</option>
                @foreach ($permissionsGrouped->keys() as $mod)
                    <option>{{ ucfirst($mod) }}</option>
                @endforeach
            </select>
            <div class="flex-1"></div>
            <span class="text-[11px] font-mono text-ink-500 mr-2">{{ $totalPermissions }} permissions in
                {{ $moduleCount }} {{ __('modules') }}</span>
        </div>

        <!-- Module groups -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            @forelse ($permissionsGrouped as $module => $perms)
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between bg-paper-50/40">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-wa-bubble text-wa-deep grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="6" cy="6" r="3" />
                                    <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5" />
                                </svg></span>
                            <span class="font-serif text-[16px] capitalize">{{ $module }}</span>
                        </div>
                        <span class="font-mono text-[11px] text-ink-500">{{ $perms->count() }}
                            {{ __('permissions') }}</span>
                    </div>
                    <div class="divide-y divide-paper-200">
                        @foreach ($perms as $perm)
                            @php $rolesUsing = (int) ($rolesPerPerm[$perm->id] ?? 0); @endphp
                            <div
                                class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-paper-50/40 @if ($rolesUsing === 0) bg-accent-amber/5 @endif">
                                <span
                                    class="font-mono text-[12.5px] @if ($rolesUsing === 0) text-accent-amber @endif">
                                    {{ $perm->name }}
                                    @if ($rolesUsing === 0)
                                        <span
                                            class="ml-1 px-1.5 py-0.5 rounded-full bg-accent-amber/15 text-accent-amber text-[9px] font-semibold">{{ __('orphan') }}</span>
                                    @endif
                                </span>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-mono text-[10.5px] @if ($rolesUsing === 0) text-accent-amber @else text-ink-500 @endif">in
                                        {{ $rolesUsing }}
                                        {{ \Illuminate\Support\Str::plural('role', $rolesUsing) }}</span>
                                    <form method="POST" action="{{ route('admin.permissions.destroy', $perm->id) }}"
                                        data-confirm="Delete permission {{ addslashes($perm->name) }}? Any role using it loses that capability immediately."
                                        data-confirm-title="{{ __('Delete permission') }}"
                                        data-confirm-text="Yes, delete" data-danger="1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="text-accent-coral hover:bg-accent-coral/10 rounded-full px-2 py-0.5 text-[10.5px]">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div
                    class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-10 text-center text-[12.5px] text-ink-500">
                    No permissions defined yet. <a href="{{ route('admin.permissions.create') }}"
                        class="text-wa-deep font-semibold hover:underline">{{ __('Create the first one') }}</a>.
                </div>
            @endforelse

        </section>
    </main>

</x-layouts.admin>

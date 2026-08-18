<x-layouts.admin :title="__('Admin · Edit role')" admin-key="roles" page="roles-edit">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/roles') }}" class="hover:text-ink-900">{{ __('Roles') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal truncate max-w-[280px]">{{ $role->name }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <span
                class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-100 text-ink-700 font-mono">{{ $role->users()->count() }}
                {{ __('users') }}</span>
            <a href="{{ url('/admin/roles') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="roleForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                Save changes
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-6 lg:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">Admin · Editing role
                #{{ $role->id }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[26px] sm:text-[30px] lg:text-[36px] leading-[1.0]"><span
                    class="italic text-wa-deep">{{ $role->name }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ count($rolePermissions) }} /
                {{ $permissions->count() }} permissions · last edited
                {{ optional($role->updated_at)->format('Y-m-d') ?? '—' }}.</p>
        </div>
    </div>

    <main class="px-4 sm:px-6 lg:px-7 pb-7">

        @if (session('status'))
            <div
                class="mb-4 rounded-2xl border border-wa-green/30 bg-wa-bubble/40 px-4 py-3 text-[12.5px] text-wa-deep">
                {{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div
                class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12.5px] text-accent-coral">
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        <form id="roleForm" method="POST" action="{{ route('admin.roles.update', $role->id) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <!-- Role name + scope -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Role details') }}</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="role-name">{{ __('Role name') }} <span class="text-accent-coral">*</span></label>
                        <input id="role-name" name="name" type="text"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            value="{{ old('name', $role->name) }}" required>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="role-scope">{{ __('Scope') }}</label>
                        <select id="role-scope" name="scope"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="workspace" selected>{{ __('Workspace level') }}</option>
                            <option value="platform">{{ __('Platform level (admin only)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="role-template">{{ __('Start from template') }}</label>
                        <select id="role-template" name="template"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">{{ __('Blank · no permissions') }}</option>
                            <option>{{ __('Owner · 28 permissions') }}</option>
                            <option>{{ __('Manager · 22 permissions') }}</option>
                            <option>{{ __('Agent · 8 permissions') }}</option>
                            <option>{{ __('Viewer · 5 permissions') }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="role-desc">{{ __('Description') }}</label>
                        <textarea id="role-desc" name="description" rows="2"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('What does this role do? Visible to admins.') }}">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Permission matrix -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                        <span class="font-serif text-[18px] leading-none">{{ __('Assign permissions') }}</span>
                        <span class="font-mono text-[10px] text-ink-500"><span id="perm-count">0</span> /
                            {{ $permissions->count() }} {{ __('selected') }}</span>
                    </div>
                    <label
                        class="inline-flex items-center gap-2 text-[12px] font-semibold cursor-pointer hover:text-wa-deep">
                        <input type="checkbox" id="grand-select"
                            class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                        Select all permissions
                    </label>
                </div>

                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th
                                class="text-left px-4 py-2.5 w-[180px] font-mono text-[10px] uppercase tracking-[0.14em]">
                                {{ __('Module') }}</th>
                            <th
                                class="text-left px-3 py-2.5 w-[140px] font-mono text-[10px] uppercase tracking-[0.14em]">
                                {{ __('Select all') }}</th>
                            <th class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                {{ __('Available permissions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200" id="perm-table">

                        @forelse ($permissionsGrouped as $module => $perms)
                            <tr class="hover:bg-paper-50/40">
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="w-7 h-7 rounded-lg bg-wa-bubble text-wa-deep grid place-items-center"><svg
                                                viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                stroke="currentColor" stroke-width="1.5">
                                                <circle cx="6" cy="6" r="3" />
                                                <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5" />
                                            </svg></span>
                                        <span class="font-semibold capitalize">{{ $module }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <label class="inline-flex items-center gap-2 text-[11.5px] cursor-pointer"><input
                                            type="checkbox"
                                            class="select-all rounded border-paper-300 text-wa-deep focus:ring-wa-deep">Select
                                        all</label>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                                        @foreach ($perms as $perm)
                                            @php
                                                $oldPerms = old('permissions');
                                                $checked = is_array($oldPerms)
                                                    ? in_array($perm->name, $oldPerms, true)
                                                    : in_array($perm->name, $rolePermissions, true);
                                            @endphp
                                            <label class="inline-flex items-center gap-2 text-[12px] cursor-pointer">
                                                <input type="checkbox" name="permissions[]"
                                                    value="{{ $perm->name }}"
                                                    class="perm rounded border-paper-300 text-wa-deep focus:ring-wa-deep"
                                                    @checked($checked)>
                                                {{ $perm->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-10 text-center text-[12.5px] text-ink-500">
                                    {{ __('No permissions defined yet.') }}
                                </td>
                            </tr>
                        @endforelse

                    </tbody>
                </table>
                </div>
            </div>

        </form>

        <!-- Danger zone -->
        @if ($role->name !== 'Super Admin')
            <div class="bg-white border border-accent-coral/30 rounded-[14px] shadow-card p-5 mt-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-accent-coral/10 text-accent-coral inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Danger zone') }}</span>
                    <span class="font-mono text-[10px] text-accent-coral ml-auto">{{ __('irreversible') }}</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <form method="POST" action="{{ route('admin.roles.duplicate', $role->id) }}"
                        class="px-3 py-2.5 rounded-lg border border-paper-200 flex items-center justify-between gap-3">
                        @csrf
                        <div>
                            <div class="text-[12.5px] font-semibold">{{ __('Duplicate role') }}</div>
                            <div class="text-[10.5px] text-ink-500 mt-0.5">
                                {{ __('Create a copy with same permissions') }}</div>
                        </div>
                        <button type="submit"
                            class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] font-semibold hover:bg-paper-50">{{ __('Duplicate') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.roles.reassign', $role->id) }}"
                        class="px-3 py-2.5 rounded-lg border border-paper-200 flex items-center justify-between gap-3">
                        @csrf
                        <div>
                            <div class="text-[12.5px] font-semibold">{{ __('Reassign all users') }}</div>
                            <div class="text-[10.5px] text-ink-500 mt-0.5">Move {{ $role->users()->count() }}
                                {{ __('users to another role') }}</div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <select name="target_role_id" required
                                class="rounded-lg border border-paper-200 bg-paper-0 px-2 py-1.5 text-[11.5px] focus:outline-none focus:border-wa-deep">
                                <option value="">{{ __('To…') }}</option>
                                @foreach ($otherRoles as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] font-semibold hover:bg-paper-50">{{ __('Move') }}</button>
                        </div>
                    </form>
                    <div
                        class="px-3 py-2.5 rounded-lg border border-accent-coral/40 bg-accent-coral/5 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[12.5px] font-semibold text-accent-coral">{{ __('Delete role') }}</div>
                            <div class="text-[10.5px] text-ink-700 mt-0.5">
                                {{ __('Removes the role and detaches all users.') }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.roles.destroy', $role->id) }}"
                            data-confirm="Delete role {{ addslashes($role->name) }}? Users assigned to this role will lose all its permissions. This cannot be undone."
                            data-confirm-title="{{ __('Delete role') }}" data-confirm-text="Yes, delete"
                            data-danger="1">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="px-3 py-1.5 rounded-full bg-accent-coral text-paper-0 text-[12px] font-semibold hover:bg-accent-coral/80">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </main>

    @push('scripts')
        <script>
            (function() {
                const table = document.getElementById('perm-table');
                if (!table) return;
                const counter = document.getElementById('perm-count');
                const grand = document.getElementById('grand-select');

                function refresh() {
                    const checked = table.querySelectorAll('input.perm:checked').length;
                    if (counter) counter.textContent = checked;
                }

                table.querySelectorAll('tr').forEach(row => {
                    const allBox = row.querySelector('input.select-all');
                    const perms = row.querySelectorAll('input.perm');
                    if (allBox) {
                        // Initial state mirrors row state.
                        allBox.checked = perms.length > 0 && Array.from(perms).every(p => p.checked);
                        allBox.addEventListener('change', () => {
                            perms.forEach(p => {
                                p.checked = allBox.checked;
                            });
                            refresh();
                        });
                    }
                    perms.forEach(p => p.addEventListener('change', () => {
                        if (allBox) allBox.checked = Array.from(perms).every(x => x.checked);
                        refresh();
                    }));
                });

                if (grand) {
                    const all = table.querySelectorAll('input.perm');
                    grand.checked = all.length > 0 && Array.from(all).every(p => p.checked);
                    grand.addEventListener('change', () => {
                        table.querySelectorAll('input.perm, input.select-all').forEach(p => {
                            p.checked = grand.checked;
                        });
                        refresh();
                    });
                }

                refresh();
            })();
        </script>
    @endpush

</x-layouts.admin>

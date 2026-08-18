<x-layouts.admin :title="__('Admin · New permission')" admin-key="permissions" page="permissions-create">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/permissions') }}" class="hover:text-ink-900">{{ __('Permissions') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ url('/admin/permissions') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="permForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                Create permission
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-6 lg:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Permissions · New') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[26px] sm:text-[30px] lg:text-[36px] leading-[1.0]">{{ __('Create a new') }}
                <span class="italic text-wa-deep">{{ __('permission') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __('Add a fine-grained capability flag. Once created, attach it to one or more roles in the role editor.') }}
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-6 lg:px-7 pb-7">

        @if ($errors->any())
            <div
                class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12.5px] text-accent-coral">
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-5">

            <!-- Form -->
            <form id="permForm" method="POST" action="{{ route('admin.permissions.store') }}"
                class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5 space-y-4">
                @csrf
                <div class="flex items-center gap-2.5">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Permission details') }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="module">{{ __('Module') }} <span class="text-accent-coral">*</span></label>
                        <select id="module" name="module"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">{{ __('Pick a module') }}</option>
                            <option {{ old('module') === 'users' ? 'selected' : '' }}>{{ __('users') }}</option>
                            <option {{ old('module') === 'roles' ? 'selected' : '' }}>{{ __('roles') }}</option>
                            <option {{ old('module') === 'permissions' ? 'selected' : '' }}>{{ __('permissions') }}
                            </option>
                            <option {{ old('module') === 'workspaces' ? 'selected' : '' }}>{{ __('workspaces') }}
                            </option>
                            <option {{ old('module') === 'metaads' ? 'selected' : '' }}>{{ __('metaads') }}</option>
                            <option {{ old('module') === 'campaigns' ? 'selected' : '' }}>{{ __('campaigns') }}
                            </option>
                            <option {{ old('module') === 'devices' ? 'selected' : '' }}>{{ __('devices') }}</option>
                            <option {{ old('module') === 'inbox' ? 'selected' : '' }}>{{ __('inbox') }}</option>
                            <option {{ old('module') === 'billing' ? 'selected' : '' }}>{{ __('billing') }}</option>
                            <option {{ old('module') === 'audit' ? 'selected' : '' }}>{{ __('audit') }}</option>
                            <option {{ old('module') === 'security' ? 'selected' : '' }}>{{ __('security') }}</option>
                            <option {{ old('module') === 'settings' ? 'selected' : '' }}>{{ __('settings') }}</option>
                            <option {{ old('module') === 'support' ? 'selected' : '' }}>{{ __('support') }}</option>
                        </select>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Permissions are grouped by module in the role editor.') }}</div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="action">{{ __('Action') }} <span class="text-accent-coral">*</span></label>
                        <input id="action" name="action" type="text" value="{{ old('action') }}"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('e.g. export · refund · approve') }}">
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Lowercase, no spaces. Will be combined as') }} <span
                                class="font-mono text-ink-700"><span id="preview-mod">{{ __('module') }}</span>.<span
                                    id="preview-act">{{ __('action') }}</span></span>.</div>
                    </div>
                    <div class="md:col-span-2">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="name">{{ __('Permission name') }} <span class="text-accent-coral">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 font-mono"
                            placeholder="{{ __('module.action — auto-filled from above') }}" required>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Auto-filled from module + action. Edit only if you need a custom string.') }}</div>
                    </div>
                    <div class="md:col-span-2">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="label">{{ __('Display label') }}</label>
                        <input id="label" name="label" type="text"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Human-readable label shown in role editor') }}">
                    </div>
                    <div class="md:col-span-2">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="desc">{{ __('Description') }}</label>
                        <textarea id="desc" name="description" rows="3"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('What does this permission grant? Visible to admins when assigning to roles.') }}"></textarea>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3 text-[11.5px] text-ink-700 leading-snug flex items-start gap-2">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep mt-0.5 shrink-0" fill="currentColor">
                        <circle cx="8" cy="8" r="6" />
                    </svg>
                    <div>
                        <b class="text-ink-900">Naming convention:</b> use <span
                            class="font-mono">{{ __('module.action') }}</span> in lowercase. Examples: <span
                            class="font-mono">{{ __('campaigns.send') }}</span>, <span
                            class="font-mono">{{ __('billing.refund') }}</span>, <span
                            class="font-mono">{{ __('audit.export') }}</span>.
                    </div>
                </div>
            </form>

            <!-- Preview / docs -->
            <aside class="space-y-3">
                <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-4">
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Permission preview') }}</div>
                    <div class="rounded-xl bg-paper-50 border border-paper-200 p-4 text-center">
                        <div class="font-mono text-[14px] text-ink-900"><span
                                id="preview-mod-2">{{ __('module') }}</span>.<span
                                id="preview-act-2">{{ __('action') }}</span></div>
                        <div class="text-[10.5px] text-ink-500 mt-1.5">{{ __('Will appear under') }} <b
                                id="preview-mod-3">selected module</b> in the role editor.</div>
                    </div>
                </div>

                <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-4">
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Code reference') }}</div>
                    <pre class="text-[11px] font-mono bg-paper-50 border border-paper-200 rounded-lg p-3 overflow-x-auto leading-relaxed">@@can('<span id="preview-full">{{ __('module.action') }}</span>')
 {{-- protected UI --}}
@@endcan</pre>
                    <div class="text-[10.5px] text-ink-500 mt-2">{{ __('Use') }} <span
                            class="font-mono">@@can()</span> in blade templates and <span
                            class="font-mono">$user-&gt;can()</span> in PHP code.</div>
                </div>

                <div
                    class="bg-wa-bubble/40 border border-paper-200 rounded-[14px] shadow-card p-3 text-[11px] text-ink-700 leading-snug">
                    <b>Tip:</b> permissions named like <span class="font-mono">{{ __('module.action') }}</span>
                    automatically group together in the role editor.
                </div>
            </aside>
        </div>
    </main>

    @push('scripts')
        <script>
            // Live preview + auto-fill the `name` field as `<module>.<action>`.
            (function() {
                const moduleSel = document.getElementById('module');
                const actionInp = document.getElementById('action');
                const nameInp = document.getElementById('name');

                const m1 = document.getElementById('preview-mod');
                const a1 = document.getElementById('preview-act');
                const m2 = document.getElementById('preview-mod-2');
                const a2 = document.getElementById('preview-act-2');
                const m3 = document.getElementById('preview-mod-3');
                const full = document.getElementById('preview-full');

                let userEditedName = false;
                if (nameInp) {
                    nameInp.addEventListener('input', () => {
                        userEditedName = true;
                    });
                }

                function refresh() {
                    const mod = (moduleSel?.value || 'module').trim();
                    const act = (actionInp?.value || 'action').trim();
                    if (m1) m1.textContent = mod;
                    if (a1) a1.textContent = act;
                    if (m2) m2.textContent = mod;
                    if (a2) a2.textContent = act;
                    if (m3) m3.textContent = mod === 'module' ? 'selected module' : mod;
                    if (full) full.textContent = `${mod}.${act}`;

                    if (nameInp && !userEditedName && moduleSel?.value && actionInp?.value) {
                        nameInp.value = `${moduleSel.value}.${actionInp.value}`;
                    }
                }

                moduleSel?.addEventListener('change', refresh);
                actionInp?.addEventListener('input', refresh);
                refresh();
            })();
        </script>
    @endpush

</x-layouts.admin>

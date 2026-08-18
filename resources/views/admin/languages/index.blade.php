<x-layouts.admin :title="__('Languages')" admin-key="languages" page="admin-languages-index">

    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Languages') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · System · Languages') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Platform') }}
                    <span class="italic text-wa-deep">{{ __('languages') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                    {{ __('Manage which languages new users and workspaces can pick. The "default" language is the fallback for every newly created account.') }}
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="rounded-2xl border border-accent-coral/30 bg-accent-coral/10 text-[#A1431F] px-4 py-2 text-[12.5px]">
                {{ session('error') }}</div>
        @endif

        <section class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['total'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('configured') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['active'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('visible to users') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Default') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 uppercase">{{ $stats['default'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('fallback for new accounts') }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Catalog') }}
                        </div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('All languages') }}</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[760px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-2.5 w-[60px] font-medium">#</th>
                            <th class="text-left px-3 py-2.5 font-medium">{{ __('Name') }}</th>
                            <th class="text-left px-3 py-2.5 w-[120px] font-medium">{{ __('Code') }}</th>
                            <th class="text-left px-3 py-2.5 w-[80px] font-medium">{{ __('Dir') }}</th>
                            <th class="text-center px-3 py-2.5 w-[100px] font-medium">{{ __('Active') }}</th>
                            <th class="text-center px-3 py-2.5 w-[100px] font-medium">{{ __('Default') }}</th>
                            <th class="text-right px-4 py-2.5 w-[100px] font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($languages as $l)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3 font-mono text-[11px]">{{ $l->sort_order }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold truncate">{{ $l->name }}</div>
                                    @if ($l->native_name)
                                        <div class="text-[10.5px] text-ink-500 truncate">{{ $l->native_name }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-[11px]">{{ $l->code }}</td>
                                <td class="px-3 py-3 font-mono text-[11px] uppercase">{{ $l->direction }}</td>
                                <td class="px-3 py-3 text-center">
                                    <form method="POST" action="{{ route('admin.languages.toggle', $l->id) }}"
                                        class="inline">@csrf
                                        <button
                                            class="px-2.5 py-1 rounded-full {{ $l->is_active ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-600 border border-paper-200' }} text-[10.5px] font-mono">{{ $l->is_active ? __('On') : __('Off') }}</button>
                                    </form>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if ($l->code === $defaultCode)
                                        <span
                                            class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0 text-[10.5px] font-mono">{{ __('Default') }}</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.languages.default', $l->id) }}"
                                            class="inline">@csrf
                                            <button
                                                class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[10.5px] font-mono">{{ __('Set') }}</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($l->code !== $defaultCode)
                                        <form method="POST" action="{{ route('admin.languages.destroy', $l->id) }}"
                                            class="inline" data-confirm="Remove {{ $l->name }}?">@csrf
                                            @method('DELETE')
                                            <button
                                                class="text-accent-coral text-[11px] hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                    {{ __('No languages yet. Add one on the right.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-[88px]">
                {{-- 20-language pack is fixed. The "Add language" form is hidden
 because every locale needs a matching lang/<code>.json file
 shipped via the fasttrans translator script. To add a 21st
 language: extend the seed migration + run the translator
 CLI + re-deploy. --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Language pack') }}</div>
                    <h3 class="font-serif text-[18px] leading-tight mt-0.5 mb-2">{{ __('Fixed at 20 languages') }}</h3>
                    <p class="text-[12px] text-ink-600 leading-relaxed">
                        {{ __('Each active language is backed by a translation JSON file shipped with the platform. New language slots will appear here when the translation pack is extended.') }}
                    </p>
                    <p class="text-[11.5px] text-ink-500 mt-3 leading-relaxed">
                        {{ __('You can still toggle active state, edit native name + direction, and pick the platform default from the table on the left.') }}
                    </p>
                </div>

                <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                    <div class="font-semibold text-[12.5px] text-wa-deep">{{ __('How this is used') }}</div>
                    <p class="text-[11.5px] text-ink-700 mt-1 leading-relaxed">
                        {{ __("Active languages show up in the user-side profile language switcher and in template / auto-reply pickers. The default applies to brand-new accounts that haven't picked one.") }}
                    </p>
                </div>
            </aside>
        </section>

    </main>

</x-layouts.admin>

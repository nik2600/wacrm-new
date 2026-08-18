@php
    $template = $template ?? null;
    $editing  = (bool) $template;
    $action   = $editing ? route('admin.flow-templates.update', $template->id) : route('admin.flow-templates.store');
    $ft       = old('flow_type', $template->flow_type ?? 'chat');
@endphp

<x-layouts.admin :title="$editing ? __('Edit template') : __('New template')" admin-key="flow-templates" page="admin-flow-templates-form">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.flow-templates.index') }}" class="hover:text-ink-900">{{ __('Flow templates') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $editing ? __('Edit') : __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('admin.flow-templates.index') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="flowTemplateForm"
                class="px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9" /></svg>
                {{ $editing ? __('Save changes') : __('Create template') }}
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Automation · ') }}{{ $editing ? __('Edit') : __('New') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">
                {{ $editing ? __('Edit') : __('New') }} <span class="italic text-wa-deep">{{ __('template') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __('Build a standard flow, export it, then register it here. Active templates appear in every tenant\'s "Start from a template" gallery on Flows.') }}
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-7 pb-7">

        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif
        <x-admin.flash />

        <form id="flowTemplateForm" method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @csrf
            @if ($editing) @method('PUT') @endif

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5 items-start">

                {{-- Left: main fields --}}
                <div class="space-y-5 min-w-0">

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('details') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Template details') }}</h2>
                        </div>
                        <div class="p-5 space-y-4">
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Name') }} <span class="text-accent-coral">*</span></span>
                                <input name="name" value="{{ old('name', $template->name ?? '') }}" required maxlength="160"
                                    placeholder="{{ __('e.g. Restaurant — Welcome & Menu') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[14px] focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Description') }}</span>
                                <textarea name="description" rows="2" maxlength="1000"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"
                                    placeholder="{{ __('One line shown to tenants in the gallery.') }}">{{ old('description', $template->description ?? '') }}</textarea>
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Category') }}</span>
                                <input name="category" value="{{ old('category', $template->category ?? '') }}" maxlength="64"
                                    placeholder="{{ __('welcome / lead / support') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('flow content') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('The flow itself') }}</h2>
                        </div>
                        <div class="p-5 space-y-4">
                            @if ($editing)
                                <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[12px] text-ink-600">
                                    {{ __('Currently :n steps. Leave all three fields empty to keep the current flow — fill one to replace it.', ['n' => $template->node_count]) }}
                                </div>
                            @else
                                <p class="text-[12.5px] text-ink-600">{{ __('Provide the flow in ONE of these ways. Easiest: open the flow builder, build it, click Export, then upload that .json here.') }}</p>
                            @endif

                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('1 · Upload exported flow (.json)') }}</span>
                                <input name="flow_file" type="file" accept="application/json,.json"
                                    class="w-full text-[12.5px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[12px] file:font-semibold file:cursor-pointer">
                            </label>

                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('2 · Or paste flow JSON') }}</span>
                                <textarea name="flow_json" rows="6" spellcheck="false"
                                    class="w-full rounded-xl border border-paper-200 bg-[#0F1720] text-[#D7E3DC] px-3 py-2.5 font-mono text-[11.5px] leading-relaxed focus:outline-none focus:border-wa-deep"
                                    placeholder='{"flowNodes":[...],"flowEdges":[...]}'>{{ old('flow_json') }}</textarea>
                            </label>

                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('3 · Or clone an existing flow by ID') }}</span>
                                <input name="source_flow_id" type="number" min="1" value="{{ old('source_flow_id') }}"
                                    placeholder="{{ __('the id in /flows/builder/<id>') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Right: settings sidebar --}}
                <div class="space-y-5 lg:sticky lg:top-[84px] self-start">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('settings') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Visibility') }}</h2>
                        </div>
                        <div class="p-5 space-y-4">
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Flow type') }}</span>
                                <select name="flow_type" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="chat" @selected($ft === 'chat')>{{ __('Chat') }}</option>
                                    <option value="call" @selected($ft === 'call')>{{ __('Call (voice)') }}</option>
                                    <option value="instagram" @selected($ft === 'instagram')>{{ __('Instagram') }}</option>
                                </select>
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Sort order') }}</span>
                                <input name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $template->sort_order ?? 0) }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Lower shows first in the gallery.') }}</span>
                            </label>
                            <label class="flex items-start gap-2.5 pt-1 cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true)) class="w-4 h-4 mt-0.5 accent-wa-deep">
                                <span class="text-[12.5px] text-ink-700">{{ __('Visible to tenants — show in the Flows "Start from a template" gallery.') }}</span>
                            </label>
                        </div>
                        <div class="px-5 py-4 border-t border-paper-200">
                            <button type="submit"
                                class="w-full px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal">
                                {{ $editing ? __('Save changes') : __('Create template') }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </form>
    </main>
</x-layouts.admin>

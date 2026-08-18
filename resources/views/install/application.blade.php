@extends('install.layout')
@section('title', 'Application')
@section('step-name', 'Application')

@section('content')
    <div class="space-y-4">
        <div class="space-y-1.5">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Step 4 of 8</div>
            <h1 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">
                Application <span class="italic text-wa-deep">basics</span>.
            </h1>
            <p class="text-[12px] text-ink-600">Name your install, set the public URL, and pick a timezone + default
                language.</p>
        </div>

        @if ($errors->any())
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12.5px] font-medium text-accent-coral">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('install.application.save') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 space-y-3">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Application name</label>
                    <input type="text" name="name" value="{{ old('name', $app['name'] ?? brand_name()) }}"
                        placeholder="{{ brand_name() }}" required autofocus
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>

                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Application URL</label>
                    <input type="url" name="url" value="{{ old('url', $app['url'] ?? url('/')) }}"
                        placeholder="https://app.example.com" required
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3" x-data="appBasics()" @click.outside="tzOpen = false">
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Timezone</label>
                    <div class="relative mt-1">
                        <input type="text" x-model="tzSearch" @focus="tzOpen = true" @input="tzOpen = true"
                            placeholder="Search timezones…"
                            class="w-full h-10 px-3 pr-9 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                        <svg class="w-3.5 h-3.5 text-ink-500 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                            viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M3 6l5 5 5-5" />
                        </svg>
                        <input type="hidden" name="timezone" :value="tzSelected">
                        <div x-show="tzOpen" x-transition x-cloak
                            class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto bg-paper-0 border border-paper-200 rounded-xl shadow-card">
                            <template x-for="tz in tzFiltered" :key="tz">
                                <div @click="tzPick(tz)"
                                    class="px-3 py-2 text-[12.5px] font-mono cursor-pointer hover:bg-wa-mint/40 hover:text-wa-deep"
                                    x-text="tz"></div>
                            </template>
                            <div x-show="tzFiltered.length === 0" class="px-3 py-2 text-[12px] text-ink-500 italic">No
                                timezones match.</div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Default language</label>
                    <select name="locale"
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                        @php
                            $locales = [
                                'en' => 'English',
                                'es' => 'Español (Spanish)',
                                'hi' => 'हिन्दी (Hindi)',
                                'ar' => 'العربية (Arabic)',
                                'pt' => 'Português',
                                'ru' => 'Русский (Russian)',
                                'ja' => '日本語 (Japanese)',
                                'de' => 'Deutsch (German)',
                                'fr' => 'Français (French)',
                                'it' => 'Italiano',
                                'ko' => '한국어 (Korean)',
                                'zh-CN' => '简体中文 (Chinese)',
                                'tr' => 'Türkçe',
                                'id' => 'Bahasa Indonesia',
                                'vi' => 'Tiếng Việt',
                                'th' => 'ไทย (Thai)',
                                'pl' => 'Polski',
                                'nl' => 'Nederlands',
                                'ur' => 'اردو (Urdu)',
                                'he' => 'עברית (Hebrew)',
                                'bn' => 'বাংলা (Bengali)',
                            ];
                            $currentLocale = old('locale', $app['locale'] ?? 'en');
                        @endphp
                        @foreach ($locales as $code => $label)
                            <option value="{{ $code }}" @selected($currentLocale === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
                <a href="{{ route('install.database') }}"
                    class="px-5 h-10 inline-flex items-center gap-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12.5px] font-semibold text-ink-700">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 8H3M7 4L3 8l4 4" />
                    </svg>
                    Back
                </a>
                <button type="submit"
                    class="px-6 h-10 inline-flex items-center gap-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold shadow-card">
                    Continue
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10M9 4l4 4-4 4" />
                    </svg>
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function appBasics() {
                return {
                    tzList: @json(timezone_identifiers_list()),
                    tzSelected: @json($app['timezone'] ?? 'UTC'),
                    tzSearch: '',
                    tzOpen: false,
                    init() {
                        this.tzSearch = this.tzSelected;
                    },
                    get tzFiltered() {
                        const q = this.tzSearch.toLowerCase().trim();
                        if (!q) return this.tzList.slice(0, 25);
                        return this.tzList.filter(t => t.toLowerCase().includes(q)).slice(0, 25);
                    },
                    tzPick(tz) {
                        this.tzSelected = tz;
                        this.tzSearch = tz;
                        this.tzOpen = false;
                    },
                };
            }
        </script>
    @endpush
@endsection

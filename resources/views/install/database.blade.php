@extends('install.layout')
@section('title', 'Database')
@section('step-name', 'Database')

@section('content')
    <div class="space-y-4">
        <div class="space-y-1.5">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Step 3 of 8</div>
            <h1 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">
                Database <span class="italic text-wa-deep">connection</span>.
            </h1>
            <p class="text-[12px] text-ink-600">Point WaDesk at an empty MySQL database. We'll verify the connection before
                saving.</p>
        </div>

        @if ($errors->any())
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12.5px] font-medium text-accent-coral">
                {{ $errors->first() }}
            </div>
        @endif

        <form x-data="dbForm()" @submit.prevent="submit($event)" method="POST"
            action="{{ route('install.database.save') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 space-y-3">
            @csrf

            {{-- Driver badge — MySQL is the only supported driver. --}}
            <div class="flex items-center justify-between gap-3">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Database driver</div>
                <div
                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-wa-mint border border-wa-green/40 text-wa-deep text-[12px] font-semibold">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                        <ellipse cx="8" cy="3.5" rx="5.5" ry="2" />
                        <path d="M2.5 3.5v9c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2v-9" />
                        <path d="M2.5 8c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2" />
                    </svg>
                    MySQL
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Host</label>
                    <input type="text" x-model="host" placeholder="127.0.0.1"
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Port</label>
                    <input type="text" x-model="port" placeholder="3306"
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>
            </div>

            <div>
                <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Database name</label>
                <input type="text" x-model="database" placeholder="wadesk"
                    class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Username</label>
                    <input type="text" x-model="username" placeholder="root"
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Password</label>
                    <input type="password" x-model="password"
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>
            </div>

            {{-- Hidden mirrors so the standard form POST carries everything --}}
            <input type="hidden" name="host" :value="host">
            <input type="hidden" name="port" :value="port">
            <input type="hidden" name="database" :value="database">
            <input type="hidden" name="username" :value="username">
            <input type="hidden" name="password" :value="password">

            {{-- Test connection --}}
            <div>
                <button type="button" @click="testConnection()" :disabled="testing"
                    class="w-full h-10 rounded-full border border-paper-200 bg-paper-50 hover:bg-paper-100 text-[12.5px] font-semibold inline-flex items-center justify-center gap-2 disabled:opacity-60">
                    <template x-if="testing">
                        <svg class="w-3.5 h-3.5 spin-slow" fill="none" viewBox="0 0 16 16" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" d="M14 8a6 6 0 1 1-6-6" />
                        </svg>
                    </template>
                    <span x-text="testing ? 'Testing…' : 'Test connection'"></span>
                </button>

                <div x-show="testResult === true" x-cloak
                    class="mt-2 rounded-xl border border-wa-green/40 bg-wa-mint/50 px-3 py-2 text-[12px] font-medium text-wa-deep">
                    <span x-text="testMessage"></span>
                </div>
                <div x-show="testResult === false" x-cloak
                    class="mt-2 rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] font-medium text-accent-coral">
                    <span x-text="testMessage"></span>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
                <a href="{{ route('install.requirements') }}"
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
            function dbForm() {
                return {
                    host: @json($db['host'] ?? '127.0.0.1'),
                    port: @json($db['port'] ?? '3306'),
                    database: @json($db['database'] ?? ''),
                    username: @json($db['username'] ?? ''),
                    password: @json($db['password'] ?? ''),
                    testing: false,
                    testResult: null,
                    testMessage: '',
                    async testConnection() {
                        this.testing = true;
                        this.testResult = null;
                        try {
                            const r = await fetch(@json(route('install.database.test')), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    host: this.host,
                                    port: this.port,
                                    database: this.database,
                                    username: this.username,
                                    password: this.password,
                                }),
                            });
                            const data = await r.json();
                            this.testResult = !!data.success;
                            this.testMessage = data.message || (data.success ? 'Connected.' : 'Connection failed.');
                        } catch (e) {
                            this.testResult = false;
                            this.testMessage = 'Network error: ' + e.message;
                        }
                        this.testing = false;
                    },
                    submit(event) {
                        const form = event.target;
                        // Route through the wizard's no-reload swap when it's loaded;
                        // fall back to a native submit otherwise.
                        if (typeof window.installGo === 'function') {
                            window.installGo(form.action, {
                                method: 'POST',
                                body: new FormData(form)
                            });
                        } else {
                            form.submit();
                        }
                    },
                }
            }
        </script>
    @endpush
@endsection

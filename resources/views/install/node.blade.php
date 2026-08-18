@extends('install.layout')
@section('title', 'Node bridge')
@section('step-name', 'Node bridge')

@section('content')
    <div class="space-y-4" x-data="nodeBridge()">
        <div class="space-y-1.5">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Step 6 of 8</div>
            <h1 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">
                Node <span class="italic text-wa-deep">bridge</span>.
            </h1>
            <p class="text-[12px] text-ink-600">WaDesk sends WhatsApp through a small Node service. Point Laravel at it and
                set the shared token — the installer writes both env files.</p>
        </div>

        @if ($errors->any())
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12.5px] font-medium text-accent-coral">
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach ($errors->all() as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('install.node.save') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 space-y-3">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Node server URL</label>
                    <input type="url" name="server_url"
                        value="{{ old('server_url', $node['server_url'] ?? 'http://localhost:8888') }}"
                        placeholder="http://localhost:8888" required autofocus
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                    <p class="mt-1 text-[10.5px] text-ink-500 leading-relaxed">Where your Node bridge listens. Laravel calls
                        this to send messages.</p>
                </div>

                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Node port</label>
                    <input type="number" name="node_port" x-model="port" min="1" max="65535"
                        value="{{ old('node_port', $node['node_port'] ?? 8888) }}" placeholder="8888" required
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                    <p class="mt-1 text-[10.5px] text-ink-500 leading-relaxed">The port the Node bridge binds to (written to
                        <span class="font-mono">node/.env</span>).</p>
                </div>
            </div>

            <div>
                <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Node shared token</label>
                <div class="relative mt-1">
                    <input type="text" name="node_token" x-model="token" required minlength="16"
                        class="w-full h-10 pl-3 pr-28 rounded-xl border border-paper-200 bg-paper-50 text-[12px] font-mono tracking-tight focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                    <button type="button" @click="generate()"
                        class="absolute right-1.5 top-1/2 -translate-y-1/2 px-3 h-7 inline-flex items-center gap-1.5 rounded-lg border border-paper-200 bg-paper-0 hover:bg-wa-mint/40 text-[11px] font-semibold text-wa-deep">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9M13.5 2.5V5H11" />
                        </svg>
                        Generate
                    </button>
                </div>
                <p class="mt-1 text-[10.5px] text-ink-500 leading-relaxed">Auto-filled. Must match in Laravel and the Node
                    bridge — the installer writes both.</p>
            </div>

            <div
                class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2 text-[10.5px] text-ink-500 font-mono leading-relaxed flex items-start gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-wa-deep" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <circle cx="8" cy="8" r="6.5" />
                    <path stroke-linecap="round" d="M8 7.5v4M8 5h.01" />
                </svg>
                <span>These write to <span class="text-ink-700">.env</span> and <span class="text-ink-700">node/.env</span>
                    automatically. The token is shared as <span class="text-ink-700">NODE_WEBHOOK_TOKEN</span> across
                    both.</span>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
                <a href="{{ route('install.admin') }}"
                    class="px-5 h-10 inline-flex items-center gap-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12.5px] font-semibold text-ink-700">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 8H3M7 4L3 8l4 4" />
                    </svg>
                    Back
                </a>
                <button type="submit"
                    class="px-6 h-10 inline-flex items-center gap-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold shadow-card">
                    Continue &amp; install
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10M9 4l4 4-4 4" />
                    </svg>
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function nodeBridge() {
                return {
                    token: @json(old('node_token', $node['node_token'] ?? '')),
                    port: @json(old('node_port', $node['node_port'] ?? 8888)),
                    generate() {
                        const bytes = new Uint8Array(32);
                        (window.crypto || window.msCrypto).getRandomValues(bytes);
                        this.token = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
                    },
                };
            }
        </script>
    @endpush
@endsection

@extends('install.layout')
@section('title', 'Installing')
@section('step-name', 'Installing')

@section('content')
    <div class="space-y-5" x-data="installer()" x-init="start()">

        <div class="space-y-1.5">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Step 7 of 8</div>
            <h1 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">
                Installing <span class="italic text-wa-deep">WaDesk</span>.
            </h1>
            <p class="text-[12px] text-ink-600">Sit tight — we're configuring everything. This usually takes 20–40 seconds.
            </p>
        </div>

        {{-- Overall progress bar --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500" x-text="progressLabel"></span>
                <span class="font-mono text-[10.5px] font-semibold text-wa-deep tabular-nums"
                    x-text="Math.round(progress) + '%'"></span>
            </div>
            <div class="w-full h-1.5 bg-paper-200 rounded-full overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-wa-teal to-wa-deep transition-all duration-700 ease-out"
                    :style="'width:' + progress + '%'"></div>
            </div>

            {{-- Substep list --}}
            <div class="mt-5 space-y-1">
                <template x-for="(step, i) in steps" :key="i">
                    <div class="flex items-center gap-3 py-2 px-2.5 rounded-xl transition-colors"
                        :class="{
                            'bg-wa-mint/30 step-enter': step.status === 'running',
                            'bg-wa-mint/15': step.status === 'done',
                            'bg-accent-coral/10': step.status === 'failed',
                        }">
                        <div class="w-5 h-5 grid place-items-center shrink-0">
                            <template x-if="step.status === 'pending'">
                                <svg class="w-4 h-4 text-ink-500/40" viewBox="0 0 16 16" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <circle cx="8" cy="8" r="6.5" />
                                </svg>
                            </template>
                            <template x-if="step.status === 'running'">
                                <svg class="w-4 h-4 text-wa-deep spin-slow" viewBox="0 0 16 16" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" d="M14 8a6 6 0 1 1-6-6" />
                                </svg>
                            </template>
                            <template x-if="step.status === 'done'">
                                <svg class="w-4 h-4 text-wa-deep" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                    stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8" />
                                </svg>
                            </template>
                            <template x-if="step.status === 'failed'">
                                <svg class="w-4 h-4 text-accent-coral" viewBox="0 0 16 16" fill="none"
                                    stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                                </svg>
                            </template>
                        </div>

                        <div class="flex-1 min-w-0">
                            <span class="text-[12.5px] transition-colors"
                                :class="{
                                    'text-ink-500/70': step.status === 'pending',
                                    'text-wa-deep font-semibold': step.status === 'running',
                                    'text-ink-900': step.status === 'done',
                                    'text-accent-coral font-semibold': step.status === 'failed',
                                }"
                                x-text="step.status === 'running' ? step.activeLabel : step.label"></span>
                        </div>

                        <div class="shrink-0 font-mono text-[10.5px] text-ink-500 tabular-nums">
                            <span x-show="step.status === 'done' && step.duration" x-text="step.duration"></span>
                            <span x-show="step.status === 'running'" class="pulse-dim text-wa-deep" x-text="elapsed"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Error block --}}
        <div x-show="error" x-cloak class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-accent-coral mb-1">Installation paused</div>
            <p class="text-[12.5px] text-ink-700 font-medium" x-text="error"></p>
        </div>
        <div x-show="error" x-cloak>
            <button @click="retry()"
                class="w-full px-6 h-11 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold shadow-card">
                Retry from failed step
            </button>
        </div>

        {{-- Success block --}}
        <div x-show="allDone" x-cloak
            class="rounded-2xl border border-wa-green/40 bg-wa-mint/40 px-4 py-3 flex items-center gap-3">
            <svg class="w-5 h-5 text-wa-deep shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                stroke-width="2.4">
                <circle cx="8" cy="8" r="6.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.5 8l2 2 3.5-4" />
            </svg>
            <span class="text-[13px] font-semibold text-wa-deep">Installation complete — redirecting…</span>
        </div>
    </div>

    @push('scripts')
        <script>
            function installer() {
                return {
                    steps: [{
                            label: 'Environment written',
                            activeLabel: 'Writing .env configuration…',
                            status: 'pending',
                            duration: null,
                            startedAt: null
                        },
                        {
                            label: 'Database tables created',
                            activeLabel: 'Running database migrations…',
                            status: 'pending',
                            duration: null,
                            startedAt: null
                        },
                        {
                            label: 'Essential data seeded',
                            activeLabel: 'Seeding roles, plans, currencies, gateways, guidebook…',
                            status: 'pending',
                            duration: null,
                            startedAt: null
                        },
                        {
                            label: 'Admin + workspace ready',
                            activeLabel: 'Creating admin account and private workspace…',
                            status: 'pending',
                            duration: null,
                            startedAt: null
                        },
                        {
                            label: 'File permissions set',
                            activeLabel: 'Linking storage and call-recording directories…',
                            status: 'pending',
                            duration: null,
                            startedAt: null
                        },
                        {
                            label: 'Installation finalized',
                            activeLabel: 'Clearing caches and writing install marker…',
                            status: 'pending',
                            duration: null,
                            startedAt: null
                        },
                    ],
                    error: null,
                    allDone: false,
                    elapsed: '0.0s',
                    _timer: null,

                    get progress() {
                        const done = this.steps.filter(s => s.status === 'done').length;
                        const running = this.steps.filter(s => s.status === 'running').length;
                        return ((done + running * 0.5) / this.steps.length) * 100;
                    },
                    get progressLabel() {
                        if (this.allDone) return 'Complete';
                        if (this.error) return 'Failed — see below';
                        const c = this.steps.find(s => s.status === 'running');
                        return c ? c.activeLabel.replace('…', '') : 'Preparing…';
                    },
                    fmt(ms) {
                        return ms < 1000 ? ms + 'ms' : (ms / 1000).toFixed(1) + 's';
                    },
                    startTimer(step) {
                        step.startedAt = Date.now();
                        this.elapsed = '0.0s';
                        this._timer = setInterval(() => {
                            this.elapsed = ((Date.now() - step.startedAt) / 1000).toFixed(1) + 's';
                        }, 100);
                    },
                    stopTimer(step) {
                        clearInterval(this._timer);
                        if (step.startedAt) step.duration = this.fmt(Date.now() - step.startedAt);
                    },
                    async runStep(i) {
                        const step = this.steps[i];
                        step.status = 'running';
                        this.startTimer(step);
                        try {
                            const r = await fetch(@json(route('install.execute')), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    step: i + 1
                                }),
                            });
                            const data = await r.json();
                            this.stopTimer(step);
                            if (r.ok && data.success) {
                                step.status = 'done';
                                return true;
                            }
                            step.status = 'failed';
                            this.error = data.message || 'Step failed.';
                            return false;
                        } catch (e) {
                            this.stopTimer(step);
                            step.status = 'failed';
                            this.error = 'Network error: ' + e.message;
                            return false;
                        }
                    },
                    async start() {
                        this.error = null;
                        for (let i = 0; i < this.steps.length; i++) {
                            if (this.steps[i].status === 'done') continue;
                            const ok = await this.runStep(i);
                            if (!ok) return;
                        }
                        this.allDone = true;
                        setTimeout(() => window.location.href = @json(url('/login')), 1400);
                    },
                    retry() {
                        this.error = null;
                        const i = this.steps.findIndex(s => s.status === 'failed');
                        const from = i === -1 ? 0 : i;
                        for (let j = from; j < this.steps.length; j++) {
                            this.steps[j].status = 'pending';
                            this.steps[j].duration = null;
                        }
                        this.start();
                    },
                };
            }
        </script>
    @endpush
@endsection

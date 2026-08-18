@extends('install.layout')
@section('title', 'Licence')
@section('step-name', 'Licence')

@section('content')
    <div class="space-y-4">
        <div class="space-y-1.5">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Licence</div>
            <h1 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">
                Verify your <span class="italic text-wa-deep">purchase</span>.
            </h1>
            <p class="text-[12px] text-ink-600">Enter your CodeCanyon purchase code. We validate it with Envato before
                installing — installation can't continue without a valid code.</p>
        </div>

        @if ($errors->any())
            <div
                class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12.5px] font-medium text-accent-coral">
                {{ $errors->first() }}
            </div>
        @endif

        @if (!empty($verified))
            <div
                class="rounded-2xl border border-wa-green/40 bg-wa-mint/40 px-4 py-2.5 text-[12.5px] font-medium text-wa-deep">
                Purchase already verified — you can continue.
            </div>
        @endif

        <form method="POST" action="{{ route('install.license.verify') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 space-y-3">
            @csrf
            <label class="block space-y-1.5">
                <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Purchase code</span>
                <input type="text" name="purchase_code" value="{{ old('purchase_code') }}" autofocus
                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                    class="w-full rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
            </label>
            <p class="text-[11px] text-ink-500 leading-relaxed">
                Find it in your CodeCanyon account → <span class="font-medium">Downloads</span> → the
                <span class="font-medium">License certificate &amp; purchase code</span> for WaDesk.
            </p>

            <div class="flex items-center justify-between pt-1">
                <a href="{{ route('install.welcome') }}"
                    class="text-[12px] text-ink-500 hover:text-ink-900 transition">← Back</a>
                <button type="submit"
                    class="px-6 h-11 inline-flex items-center gap-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold shadow-card transition">
                    Verify &amp; continue
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10M9 4l4 4-4 4" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
@endsection

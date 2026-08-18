@extends('install.layout')
@section('title', 'Requirements')
@section('step-name', 'Requirements')

@section('content')
    @php
        $extPassed = count(array_filter($extensions));
        $extTotal = count($extensions);
        $dirPassed = count(array_filter($directories));
        $dirTotal = count($directories);
    @endphp
    <div class="space-y-4">
        <div class="space-y-1.5">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Step 2 of 8</div>
            <h1 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">
                Server <span class="italic text-wa-deep">requirements</span>.
            </h1>
            <p class="text-[12px] text-ink-600">We're checking the host meets the minimum bar before installing.</p>
        </div>

        {{-- PHP version banner --}}
        @if ($phpOk)
            <div
                class="rounded-xl border border-wa-green/40 bg-wa-mint/50 px-4 py-2.5 flex items-center gap-2.5 text-[12.5px] font-semibold text-wa-deep">
                <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8" />
                </svg>
                PHP {{ $phpVersion }} <span class="text-ink-600 font-mono text-[11px] font-medium">— meets the 8.2+
                    requirement</span>
            </div>
        @else
            <div
                class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 flex items-center gap-2.5 text-[12.5px] font-semibold text-accent-coral">
                <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4">
                    <circle cx="8" cy="8" r="6.5" />
                    <path d="M8 4.5v4M8 11h.01" />
                </svg>
                PHP {{ $phpVersion }} <span class="font-mono text-[11px] font-medium">— WaDesk needs 8.2 or newer.</span>
            </div>
        @endif

        {{-- Two columns: extensions on the left, directories + PDO on the right.
 Keeps the whole check on one screen — no scroll. --}}
        <div class="grid lg:grid-cols-2 gap-3.5">
            <div class="bg-paper-50 border border-paper-200 rounded-2xl p-3.5">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">PHP extensions</span>
                    <span
                        class="font-mono text-[10.5px] font-semibold {{ $extPassed === $extTotal ? 'text-wa-deep' : 'text-accent-coral' }}">{{ $extPassed }}/{{ $extTotal }}</span>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    @foreach ($extensions as $name => $loaded)
                        <div
                            class="flex items-center justify-between rounded-lg border border-paper-200 bg-paper-0 pl-2.5 pr-2 py-1">
                            <span class="text-[11.5px] font-mono text-ink-700">{{ $name }}</span>
                            @if ($loaded)
                                <svg class="w-3.5 h-3.5 text-wa-deep shrink-0" fill="none" viewBox="0 0 16 16"
                                    stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8" />
                                </svg>
                            @else
                                <svg class="w-3.5 h-3.5 text-accent-coral shrink-0" fill="none" viewBox="0 0 16 16"
                                    stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                                </svg>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="space-y-3.5">
                <div class="bg-paper-50 border border-paper-200 rounded-2xl p-3.5">
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Directories</span>
                        <span
                            class="font-mono text-[10.5px] font-semibold {{ $dirPassed === $dirTotal ? 'text-wa-deep' : 'text-accent-coral' }}">{{ $dirPassed }}/{{ $dirTotal }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach ($directories as $path => $writable)
                            <div
                                class="flex items-center justify-between rounded-lg border border-paper-200 bg-paper-0 pl-2.5 pr-2 py-1">
                                <span class="text-[11.5px] font-mono text-ink-700 truncate">{{ $path }}</span>
                                @if ($writable)
                                    <svg class="w-3.5 h-3.5 text-wa-deep shrink-0" fill="none" viewBox="0 0 16 16"
                                        stroke="currentColor" stroke-width="2.4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8" />
                                    </svg>
                                @else
                                    <svg class="w-3.5 h-3.5 text-accent-coral shrink-0" fill="none" viewBox="0 0 16 16"
                                        stroke="currentColor" stroke-width="2.4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                                    </svg>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-paper-50 border border-paper-200 rounded-2xl p-3.5">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">PDO drivers</span>
                    <div class="flex flex-wrap gap-1.5 mt-2.5">
                        @forelse ($pdoDrivers as $driver)
                            <div
                                class="inline-flex items-center gap-1.5 rounded-lg border border-paper-200 bg-paper-0 px-2.5 py-1">
                                <svg class="w-3.5 h-3.5 text-wa-deep shrink-0" fill="none" viewBox="0 0 16 16"
                                    stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8" />
                                </svg>
                                <span class="text-[11.5px] font-mono text-ink-700">{{ $driver }}</span>
                            </div>
                        @empty
                            <div class="text-[12px] text-ink-500 italic">No PDO drivers detected.</div>
                        @endforelse
                        @if (!$hasPdoDriver)
                            <div
                                class="w-full rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-2.5 py-1.5 text-[11.5px] font-medium text-accent-coral">
                                MySQL driver is required.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        @if ($allPassed)
            <div class="flex items-center justify-between gap-3 pt-0.5">
                <a href="{{ route('install.welcome') }}"
                    class="px-5 h-10 inline-flex items-center gap-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12.5px] font-semibold text-ink-700">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 8H3M7 4L3 8l4 4" />
                    </svg>
                    Back
                </a>
                <form method="POST" action="{{ route('install.requirements.check') }}">
                    @csrf
                    <button type="submit"
                        class="px-6 h-10 inline-flex items-center gap-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold shadow-card">
                        Continue
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </button>
                </form>
            </div>
        @else
            <div class="space-y-2.5">
                <div
                    class="rounded-xl border border-accent-amber/40 bg-accent-amber/10 px-4 py-2.5 text-[12px] font-medium text-ink-700">
                    Fix the items marked in coral before continuing. The Continue button stays disabled until everything
                    passes.
                </div>
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('install.welcome') }}"
                        class="px-5 h-10 inline-flex items-center gap-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12.5px] font-semibold text-ink-700">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 8H3M7 4L3 8l4 4" />
                        </svg>
                        Back
                    </a>
                    <button type="button" disabled
                        class="px-6 h-10 rounded-full bg-paper-200 text-ink-500 text-[13px] font-semibold cursor-not-allowed">
                        Continue
                    </button>
                </div>
            </div>
        @endif
    </div>
@endsection

@extends('install.layout')
@section('title', 'Welcome')
@section('step-name', 'Welcome')

@section('content')
    <div class="space-y-5">

        <div class="space-y-2">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Welcome</div>
            <h1 class="font-serif text-[26px] leading-tight tracking-[-0.01em]">
                Let's get <span class="italic text-wa-deep">WaDesk</span> running.
            </h1>
            <p class="text-[12.5px] text-ink-600 leading-relaxed max-w-[520px]">
                Eight simple steps, about three minutes total. We'll verify your server,
                connect your database, seed every default the admin console needs, link
                the Node bridge, and create your first super-admin login.
            </p>
        </div>

        {{-- Feature highlights — same icon vocabulary used elsewhere in
 WaDesk. Sets expectations for what's about to land. --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5">
            @php
                $tiles = [
                    [
                        'Database',
                        '<rect x="3" y="3" width="10" height="3" rx="1.2"/><rect x="3" y="6.5" width="10" height="3" rx="1.2"/><rect x="3" y="10" width="10" height="3" rx="1.2"/>',
                    ],
                    [
                        'Security',
                        '<path d="M8 2l5 2v4c0 3-2.2 5.5-5 6-2.8-.5-5-3-5-6V4z"/><path d="M5.8 8l1.6 1.6 3-3"/>',
                    ],
                    [
                        'Translations',
                        '<circle cx="8" cy="8" r="6"/><path d="M2 8h12M8 2c2 2 2.5 4 2.5 6S10 12 8 14M8 2c-2 2-2.5 4-2.5 6S6 12 8 14"/>',
                    ],
                    [
                        'Admin',
                        '<circle cx="6" cy="6" r="2.5"/><path d="M2 14c0-3 1.7-5 4-5s4 2 4 5"/><circle cx="11.5" cy="7" r="2"/><path d="M9 14c0-2 1.5-3.5 3-3.5s3 1.5 3 3.5"/>',
                    ],
                ];
            @endphp
            @foreach ($tiles as [$label, $svg])
                <div class="bg-paper-0 border border-paper-200 rounded-2xl px-4 py-3.5 shadow-card">
                    <span class="w-7 h-7 rounded-lg bg-wa-mint text-wa-deep grid place-items-center mb-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">{!! $svg !!}</svg>
                    </span>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- Pre-flight summary --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">Before you start</div>
            <ul class="text-[12.5px] text-ink-700 leading-relaxed space-y-1.5 list-disc pl-5">
                <li>An empty MySQL 8 database and a user with full privileges.</li>
                <li>PHP 8.2 or newer with the standard Laravel extension set.</li>
                <li>Write access to <span class="font-mono text-[11.5px]">storage/</span> and <span
                        class="font-mono text-[11.5px]">bootstrap/cache/</span>.</li>
            </ul>
        </div>

        <div class="flex items-center justify-end">
            <a href="{{ route('install.license') }}"
                class="px-6 h-11 inline-flex items-center gap-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold shadow-card transition">
                Begin installation
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10M9 4l4 4-4 4" />
                </svg>
            </a>
        </div>
    </div>
@endsection

@extends('install.layout')
@section('title', 'Admin account')
@section('step-name', 'Admin account')

@section('content')
    <div class="space-y-4">
        <div class="space-y-1.5">
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Step 5 of 8</div>
            <h1 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">
                Admin <span class="italic text-wa-deep">account</span>.
            </h1>
            <p class="text-[12px] text-ink-600">Your first super-admin login + private workspace. Add teammates later from
                <span class="font-mono text-[11.5px]">/admin/users</span>.</p>
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

        <form method="POST" action="{{ route('install.admin.save') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4 space-y-3">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Full name</label>
                    <input type="text" name="name" value="{{ old('name', $admin['name'] ?? '') }}"
                        placeholder="Vetrick R." required autofocus
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>

                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Email</label>
                    <input type="email" name="email" value="{{ old('email', $admin['email'] ?? '') }}"
                        placeholder="you@yourdomain.com" required
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Password</label>
                    <input type="password" name="password" required minlength="8"
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>
                <div>
                    <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Confirm password</label>
                    <input type="password" name="password_confirmation" required minlength="8"
                        class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </div>
            </div>

            <div>
                <label class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Workspace name</label>
                <input type="text" name="workspace_name"
                    value="{{ old('workspace_name', $admin['workspace_name'] ?? '') }}" placeholder="My team" required
                    maxlength="120"
                    class="mt-1 w-full h-10 px-3 rounded-xl border border-paper-200 bg-paper-50 text-[13px] focus:outline-none focus:border-wa-deep focus:bg-paper-0">
            </div>

            <div
                class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2 text-[10.5px] text-ink-500 font-mono leading-relaxed">
                <span class="text-ink-700 font-semibold">Min. 8 chars · bcrypt-hashed.</span>
                WaDesk is multi-tenant — one private workspace ships pre-configured; rename it later at <span
                    class="font-mono">/admin/workspaces</span>. Your password is never stored in plain text.
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
                <a href="{{ route('install.application') }}"
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
@endsection

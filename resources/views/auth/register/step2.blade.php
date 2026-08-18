{{-- Step 2 must render in the SAME chrome as step 1.

     /register (auth/register.blade.php) branches on the auth variant:
       · variant 1  → a bespoke two-column layout with the rich signup showcase
       · variants 2-5 → defers to <x-auth-shell>

     Step 2 only ever used <x-auth-shell>, so on a variant-1 install (the default,
     and what the client is on) the LEFT PANEL visibly changed between step 1 and
     step 2 — step 1 showed the full showcase, step 2 showed the shell's sparse
     chrome. Branching identically here keeps the whole signup flow consistent.

     The left panel comes from auth/_register_showcase.blade.php — a partial whose
     own docblock says it is "the shared signup showcase used by the whole
     registration flow (register + register/step1|2|3)… one source of truth so
     every signup step matches". It had been written for exactly this and was
     never actually included anywhere. It is now. --}}
@php
    $__brandName   = (string) brand_name();
    $__authVariant = (int) \App\Models\SystemSetting::get('auth.variant', '1');
    if ($__authVariant < 1 || $__authVariant > 5) $__authVariant = 1;
@endphp

@if ($__authVariant !== 1)
    <x-auth-shell page="register" :title="__('Create your workspace / Step 2')">
        @include('auth.register._step2_form')
    </x-auth-shell>
@else
    <x-layouts.guest :title="__('Create your workspace / Step 2')" page="auth-register">

        <div class="grid lg:grid-cols-[1fr_540px] {{ fc_editing() ? 'min-h-screen' : 'h-screen overflow-hidden' }}">

            <!-- LEFT: visual showcase (identical to /register) -->
            @include('auth._register_showcase')

            <!-- RIGHT: form -->
            <main class="flex flex-col justify-center px-6 py-6 lg:px-10 overflow-y-auto">
                <div class="w-full max-w-[400px] mx-auto">
                    <a href="{{ url('/') }}" class="lg:hidden inline-flex items-center gap-2 mb-6">
                        <span class="w-8 h-8 rounded-md bg-wa-deep text-paper-0 grid place-items-center"><svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z" /></svg></span>
                        <span class="font-serif text-[22px] tracking-[-0.01em]">{{ $__brandName }}</span>
                    </a>

                    @include('auth.register._step2_form')
                </div>
            </main>

        </div>

    </x-layouts.guest>
@endif

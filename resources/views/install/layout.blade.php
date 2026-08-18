<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Install') · WaDesk</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-serif: 'Fraunces', Georgia, serif;
            --font-mono: 'JetBrains Mono', 'Menlo', monospace;
        }

        /* Lock the document to the viewport — the page itself NEVER scrolls.
 The whole installer is one centred card; only a genuinely tall
 step scrolls inside the card body. */
        html,
        body {
            height: 100%;
        }

        body {
            font-family: var(--font-sans);
            overflow: hidden;
        }

        .font-serif {
            font-family: var(--font-serif);
        }

        .font-mono {
            font-family: var(--font-mono);
        }

        .h-screen-dvh {
            height: 100vh;
            height: 100dvh;
        }

        /* Soft dot grid on the page background, behind the card. */
        .install-grid {
            background-image:
                radial-gradient(circle at 1px 1px, rgba(7, 94, 84, 0.07) 1px, transparent 0);
            background-size: 24px 24px;
        }

        /* Thin scrollbar for the rare tall-step overflow inside the body. */
        .install-scroll::-webkit-scrollbar {
            width: 7px;
        }

        .install-scroll::-webkit-scrollbar-thumb {
            background: rgba(7, 94, 84, .16);
            border-radius: 99px;
        }

        .install-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        @keyframes step-enter {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .step-enter {
            animation: step-enter 0.3s ease-out forwards;
        }

        @keyframes spin-slow {
            from {
                transform: rotate(0);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .spin-slow {
            animation: spin-slow 1.1s linear infinite;
        }

        @keyframes pulse-dim {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.45;
            }
        }

        .pulse-dim {
            animation: pulse-dim 1.6s ease-in-out infinite;
        }

        @keyframes card-in {
            from {
                opacity: 0;
                transform: translateY(10px) scale(.99);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .card-in {
            animation: card-in .45s cubic-bezier(.22, 1, .36, 1) both;
        }

        /* One-shot "flip to checkmark" played by the wizard JS on the step
 that was just completed during a no-reload step transition. */
        /* Flip the new checkmark circle in, with a little "pop" (overshoot
 scale) that then settles back to its resting size. */
        @keyframes step-flip {
            0% {
                transform: rotateY(-90deg) scale(.7);
                opacity: .2;
            }

            55% {
                transform: rotateY(0) scale(1.24);
                opacity: 1;
            }

            75% {
                transform: scale(.93);
            }

            100% {
                transform: scale(1);
            }
        }

        .step-flip {
            animation: step-flip .55s cubic-bezier(.22, 1, .36, 1) both;
        }

        /* Determinate progress ring around the active step's number circle:
 the green arc FILLS from 0 → 360 (a real progress indicator, not a
 spinner) while the next step loads, then the circle flips to the
 checkmark. --ring-deg is a registered <angle> so it can animate. */
        @property --ring-deg {
            syntax: '<angle>';
            initial-value: 0deg;
            inherits: false;
        }

        @keyframes step-ring-fill {
            from {
                --ring-deg: 0deg;
            }

            to {
                --ring-deg: 360deg;
            }
        }

        .step-loading {
            position: relative;
        }

        .step-loading::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 9999px;
            background: conic-gradient(#25d366 var(--ring-deg), rgba(37, 211, 102, .16) 0deg);
            -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2.5px), #000 calc(100% - 2.5px));
            mask: radial-gradient(farthest-side, transparent calc(100% - 2.5px), #000 calc(100% - 2.5px));
            animation: step-ring-fill .45s ease-out forwards;
            pointer-events: none;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>

<body class="bg-paper-50 antialiased text-ink-900" data-page="install-wizard">

    {{-- Page = full-viewport grid that centres the wizard card. The card has
 symmetric margins on every side, so the layout reads as a deliberate
 object rather than content floating in an empty pane. --}}
    <div class="h-screen-dvh w-full grid place-items-center p-4 sm:p-6 lg:p-8 install-grid relative overflow-hidden">

        {{-- ambient glows --}}
        <div
            class="absolute -top-32 -left-24 w-[28rem] h-[28rem] rounded-full bg-wa-mint/35 blur-[120px] pointer-events-none">
        </div>
        <div
            class="absolute -bottom-32 -right-24 w-[28rem] h-[28rem] rounded-full bg-wa-bubble/40 blur-[120px] pointer-events-none">
        </div>

        <div
            class="card-in w-full max-w-[1040px] max-h-[calc(100dvh-2rem)] sm:max-h-[calc(100dvh-3rem)] flex bg-paper-0 border border-paper-200 rounded-[26px] shadow-soft overflow-hidden relative z-10">

            {{-- LEFT — in-card rail: logo, intro, vertical stepper, footer. --}}
            <aside
                class="hidden lg:flex lg:w-[300px] shrink-0 flex-col justify-between bg-paper-50 border-r border-paper-200 relative overflow-hidden">
                <div
                    class="absolute -top-16 -right-16 w-52 h-52 rounded-full bg-wa-mint/50 blur-3xl pointer-events-none">
                </div>

                <div class="relative z-10 px-8 pt-8">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ asset('images/brand-mark.png') }}" alt="WaDesk"
                            class="w-9 h-9 rounded-xl object-contain shrink-0">
                        <span class="font-serif text-[23px] leading-none tracking-[-0.01em]">Wa<span
                                class="italic text-wa-deep">Desk</span></span>
                    </div>

                    <div class="mt-7 space-y-2">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-wa-deep">Setup wizard</div>
                        <h1 class="font-serif text-[21px] leading-[1.18] tracking-[-0.01em]">
                            Your <span class="italic text-wa-deep">WhatsApp suite</span>, ready in minutes.
                        </h1>
                    </div>
                </div>

                <div class="relative z-10 px-6 py-6 flex-1 flex items-center min-h-0">
                    <div class="w-full" id="install-stepper">
                        @include('install.partials.steps', ['currentStep' => $currentStep ?? 1])
                    </div>
                </div>

                <div class="relative z-10 px-8 pb-7">
                    <p class="text-[10px] font-mono uppercase tracking-[0.18em] text-ink-500 flex items-center gap-2.5">
                        <span class="h-px w-6 bg-paper-300"></span>
                        WaDesk installer · v1.0
                    </p>
                </div>
            </aside>

            {{-- RIGHT — card body. Header (mobile) + the step, which scrolls
 inside this column only if it ever exceeds the card height. --}}
            <main class="flex-1 min-w-0 flex flex-col">
                {{-- Mobile header (lg:hidden) --}}
                <div class="lg:hidden px-6 pt-6 pb-4 border-b border-paper-200">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <img src="{{ asset('images/brand-mark.png') }}" alt="WaDesk"
                                class="w-8 h-8 rounded-lg object-contain shrink-0">
                            <span class="font-serif text-[17px]">Wa<span class="italic text-wa-deep">Desk</span></span>
                        </div>
                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep">Step
                            {{ $currentStep ?? 1 }}/8 · @yield('step-name', 'Welcome')</span>
                    </div>
                    <div class="w-full bg-paper-200 rounded-full h-1 mt-3">
                        <div class="bg-wa-deep h-1 rounded-full transition-all duration-500"
                            style="width: {{ (($currentStep ?? 1) / 8) * 100 }}%"></div>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto install-scroll px-7 md:px-9 py-7 bg-paper-0">
                    <div id="install-content">@yield('content')</div>
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>

</html>

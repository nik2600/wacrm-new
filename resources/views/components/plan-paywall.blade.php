{{--
 In-app paywall — a slide-up bottom sheet shown over a feature page when
 the current workspace's plan does NOT include that feature. Shows the
 actual plan CARDS that unlock it so the operator can pick + upgrade.

 Source of the required feature:
 1. View::share('planPaywall', ...) set by EnforcePlanFeature on
 plan:-gated routes (calling / AI), OR
 2. the config/plan_gates.php URL-path map.

 Platform admins, unlocked plans, and non-mapped pages render nothing.
 No inline JS — links only; the slide-up is a CSS keyframe in wadesk.css.
--}}
@php
    $u = auth()->check() ? auth()->user() : null;
    $ws = $u?->currentWorkspace;

    $isAdmin = false;
    if ($u) {
        try {
            $isAdmin = $u->hasRole('Super Admin') || $u->hasRole('Admin');
        } catch (\Throwable $e) {
        }
        if (!$isAdmin) {
            $isAdmin = in_array($u->role ?? null, ['admin', 'A', 'super-admin', 'platform-admin'], true);
        }
    }

    $shared = \Illuminate\Support\Facades\View::shared('planPaywall');
    $feature = is_array($shared) ? $shared['feature'] ?? null : null;
    $label = is_array($shared) ? $shared['label'] ?? null : null;
    if (!$feature) {
        foreach ((array) config('plan_gates', []) as $pattern => $feat) {
            if (request()->is($pattern)) {
                $feature = $feat;
                break;
            }
        }
    }

    $show = false;
    $plans = collect();
    if ($u && $ws && $feature && !$isAdmin) {
        if (!\App\Services\PlanLimitGuard::hasFeature($ws, $feature)) {
            $show = true;
            $label = $label ?: ucfirst(str_replace(['access_', '_'], ['', ' '], $feature));
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('packages', $feature)) {
                    $plans = \App\Models\Package::where('status', 1)
                        ->where($feature, 1)
                        ->orderBy('sort_order')
                        ->orderBy('plan_amount')
                        ->get();
                }
            } catch (\Throwable $e) {
            }
        }
    }

    $plansUrl = \Illuminate\Support\Facades\Route::has('account.plans')
        ? route('account.plans')
        : url('/account/plans');
@endphp

@if ($show)
    <div class="plan-paywall-overlay fixed inset-0 z-[80] flex items-end justify-center px-3 sm:px-4"
        style="background-color:rgba(11,31,28,0.55);">
        <div
            class="plan-paywall-sheet w-full max-w-[920px] max-h-[94vh] overflow-y-auto bg-paper-0 rounded-t-3xl shadow-soft border border-paper-200 border-b-0 px-6 sm:px-8 pt-7 pb-8 text-center">

            <span
                class="mx-auto mb-3 w-12 h-12 rounded-2xl bg-wa-mint text-wa-deep inline-flex items-center justify-center">
                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="3.5" y="7" width="9" height="6.5" rx="1.5" />
                    <path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" />
                </svg>
            </span>
            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">{{ __('Premium feature') }}
            </div>
            <h2 class="serif font-serif text-[26px] sm:text-[30px] leading-tight mt-1 text-ink-900">
                {{ ucfirst($label) }} {{ __('is not on your plan') }}</h2>
            <p class="text-[13.5px] text-ink-600 mt-2 max-w-[52ch] mx-auto">
                {{ __('Pick a plan below that includes it — upgrade and unlock this feature instantly.') }}</p>

            @if ($plans->count())
                <div class="mt-6 flex flex-wrap items-stretch justify-center gap-3.5 text-left">
                    @foreach ($plans as $p)
                        @php
                            $isFree = $p->free || (float) $p->plan_amount === 0.0;
                            $isCustom = (bool) $p->is_custom_quote;
                            $sym = html_entity_decode($p->currency_symbol ?? '$', ENT_QUOTES, 'UTF-8');
                            $price = $isCustom
                                ? __('Custom')
                                : ($isFree
                                    ? __('Free')
                                    : $sym . number_format($p->chargeableAmount(), 0));
                            $cta = $isCustom
                                ? url('/support')
                                : ($isFree
                                    ? $plansUrl
                                    : route('user.checkout.show', $p->id));
                            $hot = (bool) $p->is_highlighted;
                        @endphp
                        <div @class([
                            'relative rounded-2xl border p-4 flex flex-col w-full sm:w-[240px]',
                            'border-wa-deep ring-2 ring-wa-deep/15 bg-wa-mint/30' => $hot,
                            'border-paper-200 bg-paper-0' => !$hot,
                        ])>
                            @if ($hot)
                                <span
                                    class="absolute -top-2 left-1/2 -translate-x-1/2 px-2 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[9.5px] font-mono uppercase tracking-[0.12em]">{{ __('Popular') }}</span>
                            @endif
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ $p->pname }}</div>
                            <div class="mt-1.5 flex items-baseline gap-1">
                                <span
                                    class="font-serif text-[26px] leading-none text-ink-900">{{ $price }}</span>
                                @if (!$isFree && !$isCustom)
                                    <span class="text-[11px] text-ink-500">/ {{ __('mo') }}</span>
                                @endif
                            </div>
                            @if ($p->subtitle)
                                <p class="text-[11.5px] text-ink-500 mt-1.5 leading-snug">
                                    {{ \Illuminate\Support\Str::limit($p->subtitle, 70) }}</p>
                            @endif
                            <a href="{{ $cta }}" @class([
                                'mt-4 px-3.5 py-2 rounded-full text-center text-[12px] font-semibold transition',
                                'bg-wa-deep text-paper-0 hover:bg-wa-teal' => $hot || !$isFree,
                                'border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700' =>
                                    $isFree && !$hot,
                            ])>
                                {{ $isCustom ? __('Talk to sales') : ($isFree ? __('Continue free') : __('Choose :plan', ['plan' => $p->pname])) }}
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 flex items-center justify-center gap-4">
                <a href="{{ url('/dashboard') }}"
                    class="text-[12.5px] font-medium text-ink-600 hover:text-ink-900">{{ __('Go back') }}</a>
                <span class="text-paper-300">·</span>
                <a href="{{ $plansUrl }}"
                    class="text-[12.5px] font-semibold text-wa-deep hover:underline inline-flex items-center gap-1">{{ __('Compare all plans') }}<svg
                        viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 4l4 4-4 4" />
                    </svg></a>
            </div>
        </div>
    </div>
@endif

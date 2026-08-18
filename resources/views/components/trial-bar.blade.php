{{--
 Free-trial bar — full-width, centered, dark banner shown across the
 user app while the current workspace is on a FREE plan with a trial
 window (workspaces.trial_ends_at).

 Hidden for platform admins (they bypass every plan restriction — same
 rule as PlanLimitGuard / EnsureTrialActive), for paid plans, and for
 free plans with no expiry. Resolve the package via the tolerant
 Workspace::package() (plan is stored as slug OR numeric id).
 No inline <script>/<style>; non-dismissible by design.
--}}
@php
    $u = auth()->check() ? auth()->user() : null;
    $ws = $u?->currentWorkspace;

    // Platform admins are never restricted — no trial bar for them.
    $isPlatformAdmin = false;
    if ($u) {
        try {
            $isPlatformAdmin = $u->hasRole('Super Admin') || $u->hasRole('Admin');
        } catch (\Throwable $e) {
        }
        if (!$isPlatformAdmin) {
            $isPlatformAdmin = in_array($u->role ?? null, ['admin', 'A', 'super-admin', 'platform-admin'], true);
        }
    }
@endphp

@if (!$isPlatformAdmin && $ws && $ws->onTrial())
    @php
        $ends = $ws->trial_ends_at;
        $secsLeft = $ends->getTimestamp() - now()->getTimestamp();
        $ended = $secsLeft <= 0;
        $daysLeft = $ended ? 0 : max(1, (int) ceil($secsLeft / 86400));
        $plansUrl = \Illuminate\Support\Facades\Route::has('account.plans')
            ? route('account.plans')
            : url('/account/plans');
    @endphp

    <div class="w-full bg-ink-900 text-paper-0">
        <div
            class="max-w-screen-2xl mx-auto px-4 py-2.5 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-center">
            @if ($ended)
                <span class="text-[12.5px] text-paper-0/85">
                    {{ __('Your') }} <strong
                        class="font-semibold text-paper-0">{{ __('free trial has ended') }}</strong>.
                    {{ __('Buy a plan to unlock all features.') }}
                </span>
                <a href="{{ $plansUrl }}"
                    class="px-4 py-1.5 rounded-full bg-wa-green text-wa-deep text-[12px] font-semibold hover:brightness-110">{{ __('Buy Now') }}</a>
            @else
                <span class="text-[12.5px] text-paper-0/85">
                    {{ __('You have') }} <strong class="font-semibold text-paper-0">{{ $daysLeft }}
                        {{ $daysLeft === 1 ? __('day') : __('days') }}</strong> {{ __('to explore this') }} <strong
                        class="font-semibold text-paper-0">{{ __('Trial account') }}</strong>.
                    {{ __('Connect your preferred channel to unlock all features.') }}
                </span>
                <span class="inline-flex items-center gap-2 shrink-0">
                    <a href="{{ url('/devices') }}"
                        class="px-4 py-1.5 rounded-full bg-wa-green text-wa-deep text-[12px] font-semibold hover:brightness-110">{{ __('Connect Channel') }}</a>
                    <a href="{{ $plansUrl }}"
                        class="px-4 py-1.5 rounded-full border border-paper-0/30 text-paper-0 text-[12px] font-semibold hover:bg-paper-0/10">{{ __('Buy Now') }}</a>
                </span>
            @endif
        </div>
    </div>
@endif

{{--
 Renewal reminder bar — same treatment as the trial bar, but for PAID
 plans in their last 7 days. If an auto-renewing subscription is active it
 offers "Cancel auto-renew"; otherwise (a one-time paid plan about to lapse)
 it offers "Renew now". Hidden for platform admins and free/lifetime plans
 (those have no plan_ends_at).
--}}
@php
    $bUser = auth()->check() ? auth()->user() : null;
    $bWs = $bUser?->currentWorkspace;
    $bAdmin = false;
    if ($bUser) {
        try {
            $bAdmin = $bUser->hasRole('Super Admin') || $bUser->hasRole('Admin');
        } catch (\Throwable $e) {
        }
        if (!$bAdmin) {
            $bAdmin = in_array($bUser->role ?? null, ['admin', 'A', 'super-admin', 'platform-admin'], true);
        }
    }
    $bEnds = $bWs?->plan_ends_at;
    $bShow = !$bAdmin && $bWs && !$bWs->onTrial() && $bEnds && $bEnds->isFuture() && $bEnds->lte(now()->addDays(7));
    $bSub = null;
    if ($bShow) {
        try {
            $bSub = \App\Models\Subscription::where('workspace_id', $bWs->id)->active()->latest('id')->first();
        } catch (\Throwable $e) {
        }
    }
@endphp

@if ($bShow)
    @php
        $bDays = max(1, (int) ceil(($bEnds->getTimestamp() - now()->getTimestamp()) / 86400));
        $bPlansUrl = \Illuminate\Support\Facades\Route::has('account.plans')
            ? route('account.plans')
            : url('/account/plans');
        // "Renew now" should go STRAIGHT to checkout for the CURRENT plan
        // (one-click renew), not dump the user on the full pricing list.
        //
        // Resolving that plan needs three attempts, because a single one is not
        // enough in practice:
        //   1. billingPackage() — scope-aware. When the admin bills per ACCOUNT
        //      rather than per workspace the plan hangs off the owner, and
        //      package() alone returns null.
        //   2. package() — the plain per-workspace resolver.
        //   3. the most recent PAID order — the only record that survives when
        //      workspaces.plan is blank (the column is dual-stored, so it is
        //      routinely empty on workspaces that are genuinely on a paid plan).
        //      This is what made the button fall through to the plans page even
        //      with an active, days-from-expiry plan.
        $bRenewUrl = $bPlansUrl;
        try {
            $bPkg = $bWs->billingPackage() ?: $bWs->package();

            if (!$bPkg || !$bPkg->id) {
                $bPaidPkgId = \App\Models\Order::query()
                    ->where('workspace_id', $bWs->id)
                    ->where('status', 'paid')
                    ->latest('id')
                    ->value('package_id');
                if ($bPaidPkgId) {
                    $bPkg = \App\Models\Package::find((int) $bPaidPkgId);
                }
            }

            // Never send someone to checkout for a plan that cannot be bought.
            // A lapsed plan resolves to the FREE package (package() downgrades
            // on expiry), and checkout for free/lifetime/quote plans is
            // meaningless — the pricing list is the right destination there.
            $bBuyable = $bPkg && $bPkg->id
                && !$bPkg->free && !$bPkg->lifetime
                && !$bPkg->is_custom_quote && !$bPkg->isFreePlan();

            if ($bBuyable && \Illuminate\Support\Facades\Route::has('checkout.show')) {
                $bRenewUrl = route('checkout.show', $bPkg->id);
            }
        } catch (\Throwable $e) { /* keep plans-page fallback */ }
    @endphp
    <div class="w-full bg-ink-900 text-paper-0">
        <div
            class="max-w-screen-2xl mx-auto px-4 py-2.5 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-center">
            @if ($bSub)
                <span class="text-[12.5px] text-paper-0/85">
                    {{ __('Your plan') }} <strong class="font-semibold text-paper-0">{{ __('auto-renews in') }}
                        {{ $bDays }} {{ $bDays === 1 ? __('day') : __('days') }}</strong> {{ __('on') }}
                    <strong class="font-semibold text-paper-0">{{ $bEnds->format('M j, Y') }}</strong>.
                </span>
                <span class="inline-flex items-center gap-2 shrink-0">
                    <a href="{{ $bPlansUrl }}"
                        class="px-4 py-1.5 rounded-full bg-wa-green text-wa-deep text-[12px] font-semibold hover:brightness-110">{{ __('Manage plan') }}</a>
                    <form method="POST" action="{{ route('account.subscription.cancel') }}"
                        onsubmit="return confirm('{{ __('Cancel auto-renew? Your plan stays active until it expires, then will not renew.') }}');">
                        @csrf
                        <button type="submit"
                            class="px-4 py-1.5 rounded-full border border-paper-0/30 text-paper-0 text-[12px] font-semibold hover:bg-paper-0/10">{{ __('Cancel auto-renew') }}</button>
                    </form>
                </span>
            @else
                <span class="text-[12.5px] text-paper-0/85">
                    {{ __('Your plan') }} <strong class="font-semibold text-paper-0">{{ __('expires in') }}
                        {{ $bDays }} {{ $bDays === 1 ? __('day') : __('days') }}</strong> {{ __('on') }}
                    <strong class="font-semibold text-paper-0">{{ $bEnds->format('M j, Y') }}</strong>.
                    {{ __('Renew to keep your features.') }}
                </span>
                <a href="{{ $bRenewUrl }}"
                    class="px-4 py-1.5 rounded-full bg-wa-green text-wa-deep text-[12px] font-semibold hover:brightness-110 shrink-0">{{ __('Renew now') }}</a>
            @endif
        </div>
    </div>
@endif

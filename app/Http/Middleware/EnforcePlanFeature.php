<?php

namespace App\Http\Middleware;

use App\Services\PlanLimitGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level plan-feature gate.
 *
 *   Route::get('/foo', ...)->middleware('plan:access_waba_calling');
 *   Route::get('/bar', ...)->middleware('plan:access_waba_calling,access_ai_voice_agent');
 *
 * Accepts one or more feature keys (comma-separated). The user must
 * have ALL of them on their current workspace's effective plan, or the
 * request is rejected.
 *
 * Platform admins bypass automatically (PlanLimitGuard handles that).
 *
 * On rejection:
 *   - JSON / AJAX requests get 403 with a structured error so the
 *     caller can render a paywall toast.
 *   - Plain page loads get a Laravel redirect to /pricing with a flash
 *     banner explaining which feature is missing.
 */
class EnforcePlanFeature
{
    public function handle(Request $request, Closure $next, string ...$featureKeys): Response
    {
        $user = Auth::user();
        if (!$user) return $next($request);

        $workspace = $user->currentWorkspace ?? null;

        foreach ($featureKeys as $key) {
            $key = trim($key);
            if ($key === '') continue;
            if (PlanLimitGuard::hasFeature($workspace, $key)) continue;

            // Missing feature.
            $label = $this->labelFor($key);

            // Mutations (POST/PUT/PATCH/DELETE) are blocked hard so the
            // action can never run server-side, even if the UI is bypassed.
            if (! $request->isMethodSafe()) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'ok'      => false,
                        'error'   => 'plan_feature_disabled',
                        'feature' => $key,
                        'message' => "Your plan doesn't include {$label}. Upgrade to unlock this feature.",
                    ], 403);
                }
                return back()->with('warning', "Your plan doesn't include {$label}. Upgrade to unlock this feature.");
            }

            // Safe page loads (GET/HEAD): let the page render, but flag the
            // paywall so the global <x-plan-paywall> slides up over it.
            // No redirect — the operator stays on the feature page.
            \Illuminate\Support\Facades\View::share('planPaywall', ['feature' => $key, 'label' => $label]);
            return $next($request);
        }

        return $next($request);
    }

    private function labelFor(string $key): string
    {
        return match ($key) {
            'access_waba_calling'    => 'WhatsApp calling',
            'access_call_recording'  => 'call recording',
            'access_ai_voice_agent'  => 'AI voice agent',
            'access_ai_chat_assistant' => 'AI chat assistant',
            'access_ai_agents'       => 'AI agents',
            'access_ai_training'     => 'AI training',
            'access_wa_storefront'   => 'WhatsApp storefront',
            'access_sales_pipeline'  => 'Sales pipeline',
            'access_sla_policies'    => 'SLA policies',
            'access_translation'     => 'translation',
            'access_instagram'       => 'Instagram automation',
            default                  => str_replace(['access_', '_'], ['', ' '], $key),
        };
    }
}

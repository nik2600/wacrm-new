<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by App\Services\PlanLimitGuard when a workspace has hit
 * (or exceeded) its package's cap on a specific resource, or when
 * a feature is disabled on the current plan.
 *
 * Caught at controller boundaries → 422 JSON for API, redirect-back
 * with-error for web forms. Renderable for the App\Exceptions\Handler.
 */
class PlanLimitReachedException extends RuntimeException
{
    public function __construct(
        public readonly string $limitKey,
        public readonly int|string|null $used = null,
        public readonly int|string|null $limit = null,
        public readonly string $reason = 'limit_reached', // 'limit_reached' | 'feature_disabled'
        string $message = '',
        ?Throwable $previous = null,
    ) {
        $message = $message !== '' ? $message : $this->buildMessage();
        parent::__construct($message, 0, $previous);
    }

    private function buildMessage(): string
    {
        if ($this->reason === 'feature_disabled') {
            return 'This feature isn\'t available on your current plan. Upgrade to unlock it.';
        }

        $label = $this->limitLabel();

        // Delete-proof quota (e.g. campaigns via checkQuota → PlanUsage): the
        // allowance only ever goes UP on create, so deleting does NOT free a
        // slot. Never tell the user to delete — only upgrading (or a new billing
        // period) adds more. Saying "delete to free up space" here was the
        // misleading part the client flagged.
        if ($this->reason === 'quota_reached') {
            return $this->limit !== null
                ? "You've reached your plan's limit of {$this->limit} {$label}. Upgrade your plan to create more."
                : "You've reached your plan's limit for {$label}. Upgrade your plan to create more.";
        }

        // Live-count limits (devices, flows, …): deleting a row genuinely frees a
        // slot, so the delete hint is accurate here.
        if ($this->limit !== null) {
            return "You've reached your plan's limit of {$this->limit} {$label}. Upgrade your plan to add more, or delete an existing one to free up space.";
        }
        return "You've reached your plan's limit for {$label}. Upgrade your plan to add more, or delete an existing one to free up space.";
    }

    /**
     * Human label for the limit key — e.g. total_campaigns_limit → "total
     * campaigns", device_limit → "devices". Uses proper pluralisation so an
     * already-plural key ("total_campaigns") doesn't become "campaignss".
     */
    private function limitLabel(): string
    {
        $label = strtolower(str_replace('_', ' ', preg_replace('/_limit$/', '', (string) $this->limitKey)));
        return \Illuminate\Support\Str::plural(\Illuminate\Support\Str::singular($label));
    }

    public function render($request)
    {
        $payload = [
            'ok'      => false,
            'error'   => 'plan_limit_reached',
            'reason'  => $this->reason,
            'key'     => $this->limitKey,
            'used'    => $this->used,
            'limit'   => $this->limit,
            'message' => $this->getMessage(),
        ];
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload, 422);
        }
        return back()->with('error', $this->getMessage());
    }
}

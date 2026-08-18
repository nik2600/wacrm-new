<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\Workspace;

/**
 * Resolves the outbound footer string for a workspace.
 *
 *   ┌──────────────────────────┬────────────────────────────────────┐
 *   │ Plan has remove_branding │ Returned footer                    │
 *   ├──────────────────────────┼────────────────────────────────────┤
 *   │ false (default)          │ platform_branding_footer (admin)   │
 *   │ true                     │ workspaces.branding_footer if set, │
 *   │                          │ else null (no footer)              │
 *   └──────────────────────────┴────────────────────────────────────┘
 *
 * Apply this to plain text bodies (append `\n\n_<footer>_`) and to the
 * `footer:` field on interactive messages (buttons, list, cta, location,
 * poll, cards). Templates are excluded — Meta-approved content can't be
 * mutated post-submission.
 *
 * Cached per-workspace within a single request lifecycle.
 */
class BrandingFooterService
{
    /** @var array<int,?string> */
    private static array $cache = [];

    /**
     * @return string|null  The footer text (≤60 chars) or null when the
     *                      workspace chose to disable footers entirely
     *                      (only possible when their plan allows it).
     */
    public static function resolve(?Workspace $workspace): ?string
    {
        // No workspace context (e.g. legacy device row with NULL
        // workspace_id, or boot-time fetch with no phone) → fall back
        // to the platform default. Cannot grant remove_branding without
        // a workspace to read the plan flag off of.
        if (!$workspace) {
            $default = (string) SystemSetting::get('platform_branding_footer', '');
            return $default === '' ? null : self::cap(trim($default));
        }

        $key = (int) $workspace->id;
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];

        $canCustomize = PlanLimitGuard::hasFeature($workspace, 'remove_branding');

        if ($canCustomize) {
            // Plan REMOVES the forced "Sent via WaDesk" footer. The workspace
            // may set their OWN footer instead; if they haven't (NULL) or have
            // cleared it (empty), NO footer is sent. We must NOT fall through
            // to the platform default here — that is the whole point of the
            // remove_branding flag (otherwise the WaDesk footer keeps showing
            // in chat, scheduled, flows, broadcasts, etc.).
            $own = $workspace->branding_footer;
            if ($own === null) return self::$cache[$key] = null;
            $footer = trim((string) $own);
            return self::$cache[$key] = ($footer === '' ? null : self::cap($footer));
        }

        // No remove_branding → platform-fixed default. Trimmed + capped to 60
        // to keep interactive messages from rejecting on Meta side.
        $default = (string) SystemSetting::get('platform_branding_footer', '');
        return self::$cache[$key] = ($default === '' ? null : self::cap(trim($default)));
    }

    /**
     * For plain-text dispatchers — wrap as "\n\n_<footer>_" (italic
     * markdown so WhatsApp renders it visually distinct from body).
     * Returns the body unchanged when no footer is configured.
     */
    public static function appendToBody(string $body, ?Workspace $workspace): string
    {
        $footer = self::resolve($workspace);
        if ($footer === null) return $body;
        // Skip if the body already ends with this exact footer (e.g.
        // operator manually typed it, or a retry pass already appended).
        $needle = "\n\n_" . $footer . "_";
        if (str_ends_with($body, $needle)) return $body;
        return $body . $needle;
    }

    private static function cap(string $s): string
    {
        return mb_strlen($s) > 60 ? mb_substr($s, 0, 60) : $s;
    }

    /** Test seam — reset the request-scoped cache. */
    public static function flushCache(): void
    {
        self::$cache = [];
    }
}

<?php

namespace App\Services\Integrations;

use App\Models\Contact;

/**
 * Resolve a free-text name (or a raw phone number) typed in Slack / mapped
 * from a Trello member to a workspace WhatsApp contact.
 *
 * Contact name + mobile are encrypted at rest, so we cannot filter in SQL —
 * we hydrate the workspace's contacts and match in memory (exact →
 * starts-with → contains). A raw phone number short-circuits to itself.
 */
class ContactResolver
{
    /** Safety cap on how many contacts we hydrate for a single lookup. */
    private const MAX_SCAN = 5000;

    /**
     * @return array{contact: ?Contact, number: ?string, matches: int, label: string}
     */
    public function resolve(int $workspaceId, string $needle): array
    {
        $needle = trim($needle);
        $miss   = ['contact' => null, 'number' => null, 'matches' => 0, 'label' => $needle];
        if ($needle === '' || $workspaceId <= 0) return $miss;

        // A phone-number-looking input is used directly (no contact lookup).
        if (preg_match('/^[\d\s+\-()]{8,}$/', $needle)) {
            $digits = preg_replace('/\D+/', '', $needle);
            if (strlen($digits) >= 8) {
                return ['contact' => null, 'number' => $digits, 'matches' => 1, 'label' => $digits];
            }
        }

        $lc = mb_strtolower($needle);
        $contacts = Contact::query()
            ->where('workspace_id', $workspaceId)
            ->latest('id')
            ->limit(self::MAX_SCAN)
            ->get(['id', 'name', 'mobile']);

        $exact = [];
        $starts = [];
        $contains = [];
        foreach ($contacts as $c) {
            $n = mb_strtolower(trim((string) $c->name));
            if ($n === '') continue;
            if ($n === $lc)               $exact[]    = $c;
            elseif (str_starts_with($n, $lc)) $starts[]   = $c;
            elseif (str_contains($n, $lc))    $contains[] = $c;
        }

        $pool  = $exact ?: ($starts ?: $contains);
        $first = $pool[0] ?? null;
        if (!$first) return $miss;

        $number = preg_replace('/\D+/', '', (string) $first->mobile);
        return [
            'contact' => $first,
            'number'  => $number !== '' ? $number : null,
            'matches' => count($pool),
            'label'   => (string) $first->name,
        ];
    }
}

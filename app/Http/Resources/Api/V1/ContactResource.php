<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ContactGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a contact in the customer API. Scramble reads this to
 * document the response. The underlying `mobile` column is presented as a
 * normalized E.164 `phone`, and the encrypted `contact_group` id array is
 * resolved to lightweight group objects.
 */
class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'phone'      => $this->toE164($this->mobile),
            'email'      => $this->email,
            'tags'       => $this->groupRefs(),
            'attributes' => is_array($this->custom_attributes) ? $this->custom_attributes : (object) [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }

    /**
     * Resolve the encrypted `contact_group` id array into id/name objects.
     * Groups are looked up once per contact; the column is encrypted at rest
     * so membership is resolved in PHP (no SQL JSON helpers).
     */
    private function groupRefs(): array
    {
        $ids = is_array($this->contact_group) ? array_map('intval', $this->contact_group) : [];
        if (empty($ids)) {
            return [];
        }

        return ContactGroup::query()
            // Scope to THIS contact's workspace. Without it a client could put
            // another workspace's group id in `group_ids` and read that group's
            // name back through this resource (cross-tenant name disclosure).
            ->where('workspace_id', $this->workspace_id)
            ->whereIn('id', $ids)
            ->get(['id', 'user_group'])
            ->map(fn (ContactGroup $g) => ['id' => $g->id, 'name' => $g->user_group])
            ->values()
            ->all();
    }

    /**
     * Best-effort E.164 normalization: strip everything but digits and a
     * leading +. A bare number (no +) gets one prepended so the output is a
     * predictable international string.
     */
    private function toE164(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $hasPlus = str_starts_with($raw, '+');
        $digits  = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        return ($hasPlus ? '+' : '+') . $digits;
    }
}

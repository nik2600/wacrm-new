<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Group\StoreContactGroupRequest;
use App\Http\Resources\Api\V1\ContactGroupResource;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contact groups — list, create and delete the named lists/segments contacts
 * belong to in the current workspace.
 *
 * Reuses the in-app ContactsController group logic: rows are scoped via
 * ContactGroup::forCurrentWorkspace(), the group name is stored in
 * `user_group`, and membership lives denormalized in each contact's encrypted
 * `contact_group` id array (no pivot). Deleting a group also detaches it from
 * every workspace contact that references it. Results are wrapped in the
 * public { data, meta } envelope.
 */
class ContactGroupController extends V1Controller
{
    /** GET /api/v1/contact-groups — list groups with member counts. */
    public function index(Request $request): JsonResponse
    {
        // Load the workspace's contacts once so per-group member counts are
        // computed in PHP (the membership array is encrypted at rest).
        $contacts = Contact::query()->forCurrentWorkspace()->get(['contact_group']);

        $groups = ContactGroup::query()
            ->forCurrentWorkspace()
            ->orderByDesc('id')
            ->get()
            ->map(function (ContactGroup $group) use ($contacts) {
                $count = $contacts->filter(function (Contact $c) use ($group) {
                    $list = is_array($c->contact_group) ? $c->contact_group : [];
                    return in_array((string) $group->id, array_map('strval', $list), true);
                })->count();

                $group->setAttribute('contacts_count', $count);

                return (new ContactGroupResource($group))->resolve();
            })
            ->values()
            ->all();

        return $this->ok($groups, ['count' => count($groups)]);
    }

    /** POST /api/v1/contact-groups — create a group. */
    public function store(StoreContactGroupRequest $request): JsonResponse
    {
        $group = ContactGroup::create([
            'user_id'      => $request->user()?->id,
            'workspace_id' => $this->workspaceId(),
            'user_group'   => $request->input('name'),
            'note'         => $request->input('note'),
            'color'        => $request->input('color'),
        ]);

        $group->setAttribute('contacts_count', 0);

        return $this->created((new ContactGroupResource($group))->resolve());
    }

    /** DELETE /api/v1/contact-groups/{id} — delete a group + detach it. */
    public function destroy(int $id): JsonResponse
    {
        $group = ContactGroup::query()->forCurrentWorkspace()->whereKey($id)->first();

        if (!$group) {
            return $this->fail('not_found', 'Contact group not found.', 404);
        }

        // Detach this group from every contact's encrypted JSON array before
        // deleting it, mirroring ContactsController::groupDestroy. Scoped to
        // the current workspace so we never touch another tenant's contacts.
        Contact::query()
            ->forCurrentWorkspace()
            ->get(['id', 'contact_group'])
            ->each(function (Contact $contact) use ($id) {
                $list = is_array($contact->contact_group) ? $contact->contact_group : [];
                $filtered = array_values(array_filter($list, fn ($g) => (string) $g !== (string) $id));
                if ($filtered !== $list) {
                    $contact->contact_group = $filtered;
                    $contact->save();
                }
            });

        $group->delete();

        return $this->ok(['id' => $id, 'deleted' => true]);
    }
}

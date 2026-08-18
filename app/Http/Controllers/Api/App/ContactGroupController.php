<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile-app contact groups (B5 · CONTACTS/GROUPS).
 *
 * Response shapes are kept byte-compatible with the existing Flutter app
 * (list: {groups:[...]}; create: {success, message, data:{group, contacts}};
 * delete/bulk-delete: {success, message, data?}), but the implementation runs
 * against OUR current models — App\Models\ContactGroup + App\Models\Contact,
 * both encrypted-at-rest and workspace-scoped.
 *
 * Multi-tenancy: every query is scoped to the authenticated Sanctum user's
 * workspace via `current_workspace_id`, with an OR-NULL fallback for legacy
 * rows that pre-date the workspace_id backfill (same pattern the models'
 * `forCurrentWorkspace` scopes use). Mutations stamp the row with both
 * workspace_id + user_id, and look rows up inside that same scope so an
 * operator can never touch another tenant's group by guessing an id.
 *
 * The group↔contact relationship in our schema is DENORMALIZED: there is no
 * pivot table. Each Contact carries an encrypted JSON array column
 * `contact_group` holding the ids of the groups it belongs to (cast
 * `encrypted:array`). Because the column is encrypted at rest, SQL JSON
 * helpers (JSON_CONTAINS / whereJsonContains, as used by the old code) do
 * NOT work — membership must be resolved in PHP after decryption. Every
 * member lookup below loads the workspace's contacts once and filters in
 * memory, mirroring ContactGroup::getContactsCountAttribute.
 */
class ContactGroupController extends Controller
{
    // -----------------------------------------------------------------
    // GET /get-contacts
    // Contract: WhatsAppController::getContacts (old). Old shape:
    // {message, totalContacts, contacts:[{id,name,mobile,msg,email,created_at}], status}.
    // The old code read the raw `contacts` table for everyone; ours is
    // workspace-scoped and decrypts name/mobile via the model casts, then
    // sorts by name in PHP (the column is encrypted, so a SQL ORDER BY name
    // would sort ciphertext, not alphabetically).
    // -----------------------------------------------------------------
    public function getContacts(Request $request): JsonResponse
    {
        try {
            $contacts = $this->scopedContactsQuery($request)
                ->get()
                ->sortBy(fn (Contact $c) => mb_strtolower((string) $c->name))
                ->map(fn (Contact $c) => [
                    'id'         => $c->id,
                    'name'       => $c->name,
                    'mobile'     => $c->mobile,
                    'msg'        => $c->msg,
                    'email'      => $c->email,
                    'created_at' => $c->created_at,
                ])
                ->values();

            return response()->json([
                'message'       => 'Contacts retrieved successfully',
                'totalContacts' => $contacts->count(),
                'contacts'      => $contacts,
                'status'        => 'success',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App\ContactGroupController@getContacts failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Error retrieving contacts',
                'error'   => $e->getMessage(),
                'status'  => 'error',
            ], 500);
        }
    }

    // -----------------------------------------------------------------
    // GET /get-contact-groups
    // Contract: WhatsAppMessageApiController::getContactGroups (old).
    // Old shape: {groups:[ <group model fields...>, numbers:[...] ]} where
    // groups that have zero member numbers are filtered out entirely.
    // -----------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        try {
            $contacts = $this->workspaceContacts($request);

            $groups = $this->scopedGroups($request)
                ->orderByDesc('id')
                ->get()
                ->map(function (ContactGroup $group) use ($contacts) {
                    $numbers = $contacts
                        ->filter(fn ($c) => $this->contactInGroup($c, $group->id))
                        ->map(fn ($c) => $c->mobile)
                        ->filter()        // drop empty/null mobiles
                        ->unique()
                        ->values();

                    // Old endpoint hides groups with no reachable numbers.
                    if ($numbers->isEmpty()) {
                        return null;
                    }

                    return $this->present($group, $numbers->count(), $numbers->all());
                })
                ->filter()
                ->values();

            return response()->json(['groups' => $groups], 200);
        } catch (\Throwable $e) {
            Log::error('App getContactGroups failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }

    // -----------------------------------------------------------------
    // POST /contact-groups
    // Contract: Api\Main\ContactGroupController::createGroupWithContacts (old).
    // Creates a group and attaches/creates contacts in one call.
    // Shape: {success, message, data:{group, contacts}}.
    // -----------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);

        // Normalize contacts so an empty/blank name falls back to "Unknown"
        // (mirrors the old controller's pre-validation merge).
        $contactsInput = $request->input('contacts', []);
        if (is_array($contactsInput)) {
            $normalized = [];
            foreach ($contactsInput as $contact) {
                if (! is_array($contact)) {
                    continue;
                }
                $name = $contact['name'] ?? null;
                $normalized[] = array_merge($contact, [
                    'name' => ($name !== null && trim((string) $name) !== '') ? $name : 'Unknown',
                ]);
            }
            $request->merge(['contacts' => $normalized]);
        } else {
            $request->merge(['contacts' => []]);
        }

        $validator = Validator::make($request->all(), [
            'group_name'        => 'required|string|max:255',
            'contacts'          => 'required|array|min:1',
            'contacts.*.name'   => 'required|string|max:255',
            'contacts.*.mobile' => 'required|string|max:20',
            'note'              => 'nullable|string',
            'status'            => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $group = DB::transaction(function () use ($request, $userId, $wsId) {
                $group = ContactGroup::create([
                    'user_id'      => $userId,
                    'workspace_id' => $wsId,
                    'user_group'   => $request->input('group_name'),
                    'note'         => $request->input('note'),
                    'status'       => $request->has('status') ? (bool) $request->input('status') : true,
                ]);

                $createdContacts = [];
                foreach ($request->input('contacts', []) as $contactData) {
                    $mobile = (string) ($contactData['mobile'] ?? '');

                    // De-dupe by mobile within the workspace. The mobile column
                    // is encrypted, so we can't WHERE on it in SQL — resolve in
                    // PHP against the already-loaded workspace contact set.
                    $existing = $this->workspaceContacts($request)
                        ->first(fn ($c) => (string) $c->mobile === $mobile && $mobile !== '');

                    if ($existing) {
                        $current = is_array($existing->contact_group) ? $existing->contact_group : [];
                        if (! in_array((string) $group->id, array_map('strval', $current), true)) {
                            $current[] = (string) $group->id;
                            $existing->contact_group = array_values(array_map('strval', $current));
                            $existing->save();
                        }
                        $createdContacts[] = $existing;
                    } else {
                        $new = Contact::create([
                            'user_id'       => $userId,
                            'workspace_id'  => $wsId,
                            'name'          => $contactData['name'],
                            'mobile'        => $mobile,
                            'country_code'  => $contactData['country_code'] ?? null,
                            'contact_group' => [(string) $group->id],
                        ]);
                        $createdContacts[] = $new;
                        // Keep same-batch dedupe correct: a repeated mobile in
                        // the same payload should attach to the just-created
                        // contact instead of inserting a duplicate row.
                        $this->contactCache?->push($new);
                    }
                }

                // Stash for the response payload (transaction returns the group).
                $group->setRelation('createdContacts', collect($createdContacts));

                return $group;
            });

            $contacts = $group->getRelation('createdContacts');

            Log::info('App contact group created with contacts', [
                'user_id'          => $userId,
                'workspace_id'     => $wsId,
                'group_id'         => $group->id,
                'contacts_created' => $contacts->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Group and contacts created successfully',
                'data'    => [
                    'group'    => $group->makeHidden('createdContacts'),
                    'contacts' => $contacts->values(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('App create contact group failed: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create group',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // -----------------------------------------------------------------
    // DELETE /contact-groups/{groupId}
    // Contract: ContactGroupController::deleteGroup.
    // Deletes a single group + detaches it from every contact's JSON array.
    // -----------------------------------------------------------------
    public function destroy(Request $request, $groupId): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);

        try {
            $group = $this->scopedGroups($request)->whereKey((int) $groupId)->first();

            if (! $group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found',
                ], 404);
            }

            DB::transaction(function () use ($request, $group) {
                $this->detachGroupsFromContacts($request, [(int) $group->id]);
                $group->delete();
            });

            Log::info('App contact group deleted', [
                'user_id'  => $userId,
                'group_id' => (int) $groupId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Group deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App delete contact group failed: ' . $e->getMessage(), [
                'user_id'  => $userId,
                'group_id' => $groupId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // -----------------------------------------------------------------
    // POST /contact-groups/bulk-delete
    // Contract: ContactGroupController::bulkDeleteGroups.
    // Accepts `group_ids` as a comma-separated string (old contract) or an
    // array of ids; deletes all owned by the current workspace.
    // -----------------------------------------------------------------
    public function bulkDelete(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);

        $validator = Validator::make($request->all(), [
            'group_ids' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Accept either a comma-separated string (old contract) or an array.
        $raw = $request->input('group_ids');
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
        $groupIds = array_values(array_unique(array_map('intval', array_filter(
            array_map('trim', array_map('strval', $ids)),
            fn ($id) => is_numeric($id) && (int) $id > 0
        ))));

        if (empty($groupIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid group IDs provided',
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $groupIds) {
                $groups = $this->scopedGroups($request)->whereIn('id', $groupIds)->get();
                $foundIds = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();
                $notFoundIds = array_values(array_diff($groupIds, $foundIds));

                if (empty($foundIds)) {
                    return ['found' => [], 'not_found' => $notFoundIds, 'deleted' => 0];
                }

                $this->detachGroupsFromContacts($request, $foundIds);

                $deleted = $this->scopedGroups($request)->whereIn('id', $foundIds)->delete();

                return ['found' => $foundIds, 'not_found' => $notFoundIds, 'deleted' => (int) $deleted];
            });

            if (empty($result['found'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No groups found for the provided IDs',
                ], 404);
            }

            $message = "Successfully deleted {$result['deleted']} group(s)";
            if (! empty($result['not_found'])) {
                $message .= '. Group IDs not found or unauthorized: ' . implode(', ', $result['not_found']);
            }

            Log::info('App contact groups bulk deleted', [
                'user_id'       => $userId,
                'deleted_count' => $result['deleted'],
                'deleted_ids'   => $result['found'],
                'not_found_ids' => $result['not_found'],
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => [
                    'deleted_count' => $result['deleted'],
                    'deleted_ids'   => $result['found'],
                    'not_found_ids' => $result['not_found'],
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App bulk delete contact groups failed: ' . $e->getMessage(), [
                'user_id'   => $userId,
                'group_ids' => $groupIds,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete groups',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // -----------------------------------------------------------------
    // POST /contacts — add a single contact (workspace-scoped).
    // Dedupes by mobile so a re-add returns the existing row instead
    // of creating a duplicate (encrypted-at-rest column, matched in PHP).
    // Optional `group_ids[]` attaches to one or more contact groups.
    // -----------------------------------------------------------------
    public function storeContact(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'mobile'         => 'required|string|max:32',
            'country_code'   => 'nullable|string|max:8',
            'email'          => 'nullable|email|max:191',
            'company'        => 'nullable|string|max:191',
            'group_ids'      => 'nullable|array',
            'group_ids.*'    => 'integer',
            'note'           => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);
        $mobile = preg_replace('/\D+/', '', (string) $request->input('mobile')) ?: '';
        if ($mobile === '') {
            return response()->json(['success' => false, 'message' => 'mobile must contain digits.'], 422);
        }

        // Dedupe by mobile within workspace (encrypted column → match in PHP).
        $existing = $this->scopedContactsQuery($request)
            ->get()
            ->first(fn (Contact $c) => preg_replace('/\D+/', '', (string) $c->mobile) === $mobile);

        if ($existing) {
            // Merge group attachments if the caller passed group_ids.
            if ($request->filled('group_ids')) {
                $current = is_array($existing->contact_group) ? array_map('strval', $existing->contact_group) : [];
                foreach ((array) $request->input('group_ids') as $gid) {
                    $gid = (string) (int) $gid;
                    if ($gid !== '0' && ! in_array($gid, $current, true)) $current[] = $gid;
                }
                $existing->contact_group = array_values(array_unique($current));
                $existing->save();
            }
            return response()->json([
                'success' => true,
                'message' => 'Contact already exists — returned existing row.',
                'data'    => $this->presentContact($existing),
            ], 200);
        }

        $groupIds = $request->filled('group_ids')
            ? array_values(array_unique(array_map('strval', array_map('intval', (array) $request->input('group_ids')))))
            : [];

        $contact = Contact::create([
            'user_id'        => $userId,
            'workspace_id'   => $wsId,
            'name'           => $request->input('name'),
            'mobile'         => $mobile,
            'country_code'   => $request->input('country_code'),
            'email'          => $request->input('email'),
            'company'        => $request->input('company'),
            'msg'            => $request->input('note'),
            'contact_group'  => $groupIds,
        ]);

        Log::info('App contact stored', [
            'user_id' => $userId, 'workspace_id' => $wsId, 'contact_id' => $contact->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contact added.',
            'data'    => $this->presentContact($contact),
        ], 201);
    }

    // -----------------------------------------------------------------
    // GET /contacts/{id} — fetch a single contact (workspace-scoped).
    // -----------------------------------------------------------------
    public function showContact(Request $request, int $id): JsonResponse
    {
        $contact = $this->scopedContactsQuery($request)->find($id);
        if (! $contact) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }
        return response()->json([
            'success' => true,
            'data'    => $this->presentContact($contact),
        ]);
    }

    // -----------------------------------------------------------------
    // PATCH /contacts/{id} — update a single contact's fields.
    // -----------------------------------------------------------------
    public function updateContact(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'sometimes|string|max:255',
            'mobile'       => 'sometimes|string|max:32',
            'country_code' => 'sometimes|nullable|string|max:8',
            'email'        => 'sometimes|nullable|email|max:191',
            'company'      => 'sometimes|nullable|string|max:191',
            'note'         => 'sometimes|nullable|string|max:1000',
            'group_ids'    => 'sometimes|array',
            'group_ids.*'  => 'integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $contact = $this->scopedContactsQuery($request)->find($id);
        if (! $contact) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }

        if ($request->filled('name'))         $contact->name         = (string) $request->input('name');
        if ($request->filled('mobile'))       $contact->mobile       = preg_replace('/\D+/', '', (string) $request->input('mobile'));
        if ($request->has('country_code'))    $contact->country_code = $request->input('country_code');
        if ($request->has('email'))           $contact->email        = $request->input('email');
        if ($request->has('company'))         $contact->company      = $request->input('company');
        if ($request->has('note'))            $contact->msg          = $request->input('note');
        if ($request->has('group_ids')) {
            $contact->contact_group = array_values(array_unique(
                array_map('strval', array_map('intval', (array) $request->input('group_ids')))
            ));
        }
        $contact->save();

        return response()->json([
            'success' => true,
            'message' => 'Contact updated.',
            'data'    => $this->presentContact($contact),
        ]);
    }

    // -----------------------------------------------------------------
    // DELETE /contacts/{id} — delete a single contact (workspace-scoped).
    // -----------------------------------------------------------------
    public function destroyContact(Request $request, int $id): JsonResponse
    {
        $contact = $this->scopedContactsQuery($request)->find($id);
        if (! $contact) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }
        $contact->delete();
        return response()->json(['success' => true, 'message' => 'Contact deleted.']);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** Shape one contact for the API contract. */
    private function presentContact(Contact $c): array
    {
        return [
            'id'             => $c->id,
            'name'           => (string) ($c->name ?? ''),
            'mobile'         => (string) ($c->mobile ?? ''),
            'country_code'   => $c->country_code,
            'email'          => $c->email,
            'company'        => $c->company ?? null,
            'note'           => $c->msg,
            'group_ids'      => is_array($c->contact_group)
                ? array_values(array_map('intval', $c->contact_group))
                : [],
            'created_at'     => $c->created_at?->toIso8601String(),
            'updated_at'     => $c->updated_at?->toIso8601String(),
        ];
    }


    /**
     * Groups visible to the current workspace. Matches the model scopes:
     * workspace_id == current_workspace_id, OR (legacy) workspace_id NULL
     * AND user_id == the current user.
     */
    private function scopedGroups(Request $request)
    {
        $userId = (int) ($request->user()?->id ?? 0);
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);

        return ContactGroup::query()->where(function ($q) use ($wsId, $userId) {
            $q->where('workspace_id', $wsId)
                ->orWhere(function ($qq) use ($userId) {
                    $qq->whereNull('workspace_id')->where('user_id', $userId);
                });
        });
    }

    /**
     * Same workspace scope, for contacts. Loaded once per request so
     * encrypted-array membership filtering happens in PHP (no SQL JSON).
     */
    private function scopedContactsQuery(Request $request)
    {
        $userId = (int) ($request->user()?->id ?? 0);
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);

        return Contact::query()->where(function ($q) use ($wsId, $userId) {
            $q->where('workspace_id', $wsId)
                ->orWhere(function ($qq) use ($userId) {
                    $qq->whereNull('workspace_id')->where('user_id', $userId);
                });
        });
    }

    /** Cache the workspace's contacts for the lifetime of the request. */
    private ?\Illuminate\Support\Collection $contactCache = null;

    private function workspaceContacts(Request $request): \Illuminate\Support\Collection
    {
        if ($this->contactCache === null) {
            $this->contactCache = $this->scopedContactsQuery($request)->get();
        }

        return $this->contactCache;
    }

    private function contactInGroup(Contact $contact, int $groupId): bool
    {
        $list = is_array($contact->contact_group) ? $contact->contact_group : [];

        return in_array((string) $groupId, array_map('strval', $list), true);
    }

    /**
     * Shape a single group for the list response. Keeps the group's own
     * fields (decrypted by the model casts) and appends `numbers` +
     * `members_count`, mirroring the old `$group->numbers = ...` append.
     */
    private function present(ContactGroup $group, int $count, array $numbers): array
    {
        return [
            'id'            => $group->id,
            'user_group'    => $group->user_group,
            'name'          => $group->user_group,
            'note'          => $group->note,
            'status'        => $group->status,
            'color'         => $group->color,
            'created_at'    => $group->created_at,
            'updated_at'    => $group->updated_at,
            'members_count' => $count,
            'numbers'       => array_values($numbers),
        ];
    }

    /**
     * Strip the given group ids out of every workspace contact's
     * `contact_group` array. Mirrors the old detachGroupsFromContacts but
     * decrypts/re-encrypts in PHP because the column is encrypted at rest.
     */
    private function detachGroupsFromContacts(Request $request, array $groupIds): void
    {
        $strIds = array_map('strval', $groupIds);

        foreach ($this->workspaceContacts($request) as $contact) {
            $current = is_array($contact->contact_group) ? array_map('strval', $contact->contact_group) : [];
            if (empty(array_intersect($current, $strIds))) {
                continue;
            }
            $contact->contact_group = array_values(array_diff($current, $strIds));
            $contact->save();
        }

        // Invalidate the cache so a follow-up read in the same request
        // (e.g. bulk delete re-detaching) sees the mutated arrays.
        $this->contactCache = null;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Contact\StoreContactRequest;
use App\Http\Requests\Api\V1\Contact\UpdateContactRequest;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contacts — list, read, create, update and delete the current workspace's
 * contact book.
 *
 * Reuses the same workspace-shared visibility + storage shape as the in-app
 * ContactsController: rows are scoped via Contact::forCurrentWorkspace()
 * (workspace_id match, OR-NULL legacy fallback to the owner), the `mobile`
 * column carries the country code prefix, and group membership lives in the
 * encrypted `contact_group` JSON id array (no pivot table). Results are
 * wrapped in the public { data, meta } envelope.
 */
class ContactController extends V1Controller
{
    /** GET /api/v1/contacts — paginated list, optional ?search. */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $search  = trim((string) $request->input('search', ''));

        // name/mobile/email are encrypted at rest, so a SQL LIKE can't match
        // plaintext. Load the workspace's contacts and filter in PHP, then
        // paginate the resulting collection manually.
        $all = Contact::query()
            ->forCurrentWorkspace()
            ->orderByDesc('created_at')
            ->get();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $all = $all->filter(function (Contact $c) use ($needle) {
                return str_contains(mb_strtolower((string) $c->name), $needle)
                    || str_contains(mb_strtolower((string) $c->mobile), $needle)
                    || str_contains(mb_strtolower((string) $c->email), $needle);
            })->values();
        }

        $total = $all->count();
        $page  = max((int) $request->input('page', 1), 1);
        $items = $all->slice(($page - 1) * $perPage, $perPage)->values();

        $data = $items->map(fn (Contact $c) => (new ContactResource($c))->resolve())->all();

        return $this->ok($data, [
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'last_page' => (int) max(ceil($total / $perPage), 1),
        ]);
    }

    /** GET /api/v1/contacts/{id} — read a single contact. */
    public function show(int $id): JsonResponse
    {
        $contact = Contact::query()->forCurrentWorkspace()->whereKey($id)->first();

        if (!$contact) {
            return $this->fail('not_found', 'Contact not found.', 404);
        }

        return $this->ok((new ContactResource($contact))->resolve());
    }

    /** POST /api/v1/contacts — create a contact. */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $wsId = $this->workspaceId();

        // Build the display name from name parts when `name` is blank,
        // mirroring ContactsController::store.
        $fullName = $request->input('name')
            ?: trim(implode(' ', array_filter([
                $request->input('title'),
                $request->input('first_name'),
                $request->input('middle_name'),
                $request->input('last_name'),
            ])));

        $contact = Contact::create([
            'user_id'         => $request->user()?->id,
            'workspace_id'    => $wsId,
            'title'           => $request->input('title'),
            'first_name'      => $request->input('first_name'),
            'middle_name'     => $request->input('middle_name'),
            'last_name'       => $request->input('last_name'),
            'name'            => $fullName,
            'language'        => $request->input('language'),
            'address'         => $request->input('address'),
            'contact_group'   => array_map('strval', $request->input('group_ids', [])),
            'email'           => $request->input('email'),
            'country_code'    => $request->input('country_code'),
            'mobile'          => $this->composeMobile($request->input('phone'), $request->input('country_code')),
            'msg'             => $request->input('note'),
            'custom_attributes' => $request->input('attributes') ?: null,
            'is_unsubscribed' => $request->boolean('is_unsubscribed'),
        ]);

        // Webhook: contact_created (single-create path; bulk import stays
        // silent to avoid a webhook storm — same policy as contact_updated).
        \App\Services\WebhookService::emit('contact_created', [
            'workspace_id' => $contact->workspace_id,
            'user_id'      => $contact->user_id,
            'contact_id'   => $contact->id,
            'name'         => $contact->name,
            'phone_number' => preg_replace('/\D+/', '', (string) $contact->mobile) ?: null,
            'email'        => $contact->email,
            'timestamp'    => now()->timestamp,
        ], $contact->user_id);

        return $this->created((new ContactResource($contact))->resolve());
    }

    /** PUT /api/v1/contacts/{id} — update a contact (only present fields). */
    public function update(UpdateContactRequest $request, int $id): JsonResponse
    {
        $contact = Contact::query()->forCurrentWorkspace()->whereKey($id)->first();

        if (!$contact) {
            return $this->fail('not_found', 'Contact not found.', 404);
        }

        foreach (['title', 'first_name', 'middle_name', 'last_name', 'language', 'address', 'email', 'country_code'] as $field) {
            if ($request->has($field)) {
                $contact->{$field} = $request->input($field);
            }
        }

        if ($request->has('name') || $request->has('first_name')) {
            $fullName = $request->input('name')
                ?: trim(implode(' ', array_filter([
                    $contact->title,
                    $contact->first_name,
                    $contact->middle_name,
                    $contact->last_name,
                ])));
            if ($fullName !== '') {
                $contact->name = $fullName;
            }
        }

        if ($request->has('phone')) {
            $contact->mobile = $this->composeMobile($request->input('phone'), $request->input('country_code', $contact->country_code));
        }

        if ($request->has('note')) {
            $contact->msg = $request->input('note');
        }

        if ($request->has('group_ids')) {
            $contact->contact_group = array_map('strval', $request->input('group_ids', []));
        }

        if ($request->has('attributes')) {
            $contact->custom_attributes = $request->input('attributes') ?: null;
        }

        if ($request->has('is_unsubscribed')) {
            $contact->is_unsubscribed = $request->boolean('is_unsubscribed');
        }

        $contact->save();

        return $this->ok((new ContactResource($contact))->resolve());
    }

    /** DELETE /api/v1/contacts/{id} — delete a contact. */
    public function destroy(int $id): JsonResponse
    {
        $contact = Contact::query()->forCurrentWorkspace()->whereKey($id)->first();

        if (!$contact) {
            return $this->fail('not_found', 'Contact not found.', 404);
        }

        $contact->delete();

        return $this->ok(['id' => $id, 'deleted' => true]);
    }

    /**
     * Prefix the country code onto the number when it isn't already there,
     * matching the storage convention in ContactsController.
     */
    private function composeMobile(?string $phone, ?string $countryCode): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }
        if ($countryCode && !str_starts_with($phone, (string) $countryCode)) {
            return $countryCode . ' ' . $phone;
        }

        return $phone;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Single controller for both contacts and contact groups.
 * Group-related methods are prefixed with `group` to avoid name collisions.
 *
 * Methods:
 *   index, store, update, destroy, bulkDelete, import
 *   groupIndex, groupStore, groupUpdate, groupDestroy
 */
class ContactsController extends Controller
{
    // -----------------------------------------------------------------
    // Workspace-scoping helpers — every read/write goes through these so
    // a contact in Workspace A can never be edited/deleted from a session
    // signed into Workspace B. Pre-migration rows where workspace_id is
    // still NULL fall back to user ownership.
    // -----------------------------------------------------------------

    /**
     * Build the workspace-shared visibility clause used by every read
     * + write in this controller. The main branch matches the current
     * workspace (so any teammate sees rows their colleagues added);
     * the legacy branch is only for pre-migration rows where
     * `workspace_id` is still NULL.
     */
    private function workspaceScopeClause(Request $request): \Closure
    {
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);
        $userId = (int) ($request->user()?->id ?? 0);
        return function ($q) use ($wsId, $userId) {
            $q->where('workspace_id', $wsId)
              ->orWhere(function ($qq) use ($userId) {
                  $qq->whereNull('workspace_id')->where('user_id', $userId);
              });
        };
    }

    private function findContactInCurrentWorkspace(Request $request, int $id): Contact
    {
        return Contact::query()
            ->where('id', $id)
            ->where($this->workspaceScopeClause($request))
            ->firstOrFail();
    }

    private function findGroupInCurrentWorkspace(Request $request, int $id): ContactGroup
    {
        return ContactGroup::query()
            ->where('id', $id)
            ->where($this->workspaceScopeClause($request))
            ->firstOrFail();
    }

    /**
     * GET /contacts/{id}/tags — the contact's tags + all workspace tags for the
     * picker. Powers the Tags control in the edit modal.
     */
    public function contactTags(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $contact = $this->findContactInCurrentWorkspace($request, $id);
        $wsId = (int) ($request->user()->current_workspace_id ?? $contact->workspace_id ?? 0);
        return response()->json([
            'ok'   => true,
            'tags' => $contact->tags()->get(['tags.id', 'tags.name', 'tags.color']),
            'all'  => \App\Models\Tag::where('workspace_id', $wsId)->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * POST /contacts/{id}/tags — attach a tag (find-or-create by name, or by
     * tag_id) and FIRE the flow `tag_added` trigger. This is the missing piece
     * that lets audience flows enroll a contact from the contacts section.
     */
    public function attachTag(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $contact = $this->findContactInCurrentWorkspace($request, $id);
        $wsId = (int) ($request->user()->current_workspace_id ?? $contact->workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 422);

        $data = $request->validate([
            'tag_id' => 'nullable|integer',
            'name'   => 'nullable|string|max:80',
        ]);

        $tag = null;
        if (!empty($data['tag_id'])) {
            $tag = \App\Models\Tag::where('id', (int) $data['tag_id'])->where('workspace_id', $wsId)->first();
        }
        if (!$tag && !empty($data['name'])) {
            $name = trim($data['name']);
            $slug = \Illuminate\Support\Str::slug($name) ?: \Illuminate\Support\Str::random(8);
            $tag = \App\Models\Tag::firstOrCreate(
                ['workspace_id' => $wsId, 'slug' => $slug],
                ['name' => $name, 'color' => '#075E54'],
            );
        }
        if (!$tag) return response()->json(['ok' => false, 'error' => 'tag_required'], 422);

        // Only fire the trigger when the tag is NEWLY attached (idempotent) so
        // re-adding an existing tag can't re-enroll the contact.
        $already = $contact->tags()->where('tags.id', $tag->id)->exists();
        $contact->tags()->syncWithoutDetaching([$tag->id => ['added_by' => $request->user()->id]]);
        if (!$already) {
            try { app(\App\Services\Flow\FlowEnrollmentService::class)->onTagAdded($contact, (int) $tag->id); }
            catch (\Throwable $e) { \Log::warning('[CONTACT-TAG] onTagAdded failed: ' . $e->getMessage()); }
        }

        return response()->json(['ok' => true, 'tag' => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color]]);
    }

    /** DELETE /contacts/{id}/tags/{tagId} — remove a tag from the contact. */
    public function detachTag(Request $request, int $id, int $tagId): \Illuminate\Http\JsonResponse
    {
        $contact = $this->findContactInCurrentWorkspace($request, $id);
        $contact->tags()->detach($tagId);
        return response()->json(['ok' => true]);
    }

    /**
     * Canonicalise a phone to "<digits, country-code-prefixed>" so the same
     * number stored two different ways (single-add prepends the country code
     * into `mobile`; import keeps them separate) compares equal. Shared by
     * the import de-dup and the dedupe() cleanup so both agree on identity.
     */
    private function canonPhone(?string $cc, ?string $mobile): string
    {
        $m = preg_replace('/\D+/', '', (string) $mobile);
        $c = preg_replace('/\D+/', '', (string) $cc);
        if ($m === '') return '';
        if ($c !== '' && !str_starts_with($m, $c)) $m = $c . $m;
        return $m;
    }

    // -----------------------------------------------------------------
    // Contacts
    // -----------------------------------------------------------------

    public function index(Request $request): View
    {
        // Workspace-scope every read. Pre-migration rows had only
        // `user_id`, so the OR-NULL fallback keeps them visible to
        // their original owner until they re-pair/re-import.
        $wsId = (int) ($request->user()?->current_workspace_id ?? 0);
        $userId = (int) ($request->user()?->id ?? 0);
        $scopeContact = fn ($q) => $q->where(function ($qq) use ($wsId, $userId) {
            $qq->where('workspace_id', $wsId)
               ->orWhere(function ($qqq) use ($userId) { $qqq->whereNull('workspace_id')->where('user_id', $userId); });
        });
        $scopeGroup = $scopeContact; // groups follow the same shape

        // with('tags') — the Tags column renders a chip per tag on every row;
        // without eager loading that is one query per contact.
        $allContacts = Contact::query()->tap($scopeContact)->with('tags')->orderByDesc('created_at')->get();
        $groups   = ContactGroup::query()->tap($scopeGroup)->orderByDesc('created_at')->get();

        // #5/#7 — server-side group + search filters so they span the
        // WHOLE contact set, not just the 12 rows on the current page.
        // mobile/name/email are encrypted (no SQL LIKE possible), so we
        // filter the decrypted in-memory collection. $allContacts stays
        // unfiltered above so the KPI strip + per-group counts below keep
        // reporting workspace-wide totals regardless of the active filter.
        $searchQ  = trim((string) $request->query('q', ''));
        $groupSel = trim((string) $request->query('group', ''));

        $filtered = $allContacts;
        if ($groupSel === 'no_group') {
            $filtered = $filtered->filter(fn ($c) => empty($c->contact_group))->values();
        } elseif ($groupSel !== '' && $groupSel !== 'all' && ctype_digit($groupSel)) {
            $filtered = $filtered->filter(function ($c) use ($groupSel) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                return in_array($groupSel, array_map('strval', $list), true);
            })->values();
        }
        // Tag filter — pairs with the Tags column and the tag chips in the
        // sidebar. Runs on the already-eager-loaded relation, no extra query.
        $tagSel = trim((string) $request->query('tag', ''));
        if ($tagSel !== '' && ctype_digit($tagSel)) {
            $filtered = $filtered->filter(
                fn ($c) => $c->tags->contains('id', (int) $tagSel)
            )->values();
        }
        if ($searchQ !== '') {
            $filtered = $filtered->filter(function ($c) use ($searchQ) {
                foreach (['name', 'first_name', 'last_name', 'mobile', 'email'] as $f) {
                    if (mb_stripos((string) ($c->{$f} ?? ''), $searchQ) !== false) return true;
                }
                return false;
            })->values();
        }

        // Page size — the book can run into the thousands, so let the operator
        // show more rows per page (and therefore bulk-select more at once).
        // Whitelisted so a crafted ?per_page= can't ask for an unbounded page.
        $allowedPerPage = [12, 50, 100, 500, 1000];
        $perPage = (int) $request->integer('per_page', 12);
        if (!in_array($perPage, $allowedPerPage, true)) $perPage = 12;

        $contacts = $this->paginateCollection($filtered, $request, $perPage);

        // Imported count — only meaningful if the contacts table tracks a `source` column.
        $importedCount = 0;
        try {
            if (Schema::hasColumn('contacts', 'source')) {
                $importedCount = Contact::query()->tap($scopeContact)->where('source', 'import')->count();
            }
        } catch (\Throwable $e) {
            $importedCount = 0;
        }

        // No-group count — `contact_group` is encrypted JSON, so filter in PHP.
        $noGroupCount = $allContacts->filter(fn ($c) => empty($c->contact_group))->count();

        $stats = [
            'total_contacts' => $allContacts->count(),
            'total_groups'   => $groups->count(),
            'subscribed'     => $allContacts->where('is_unsubscribed', false)->count(),
            'unsubscribed'   => $allContacts->where('is_unsubscribed', true)->count(),
            // Sidebar aliases / extras
            'all_contacts'   => $allContacts->count(),
            'groups_total'   => $groups->count(),
            'imported'       => $importedCount,
            'no_group'       => $noGroupCount,
            // Contacts that belong to AT LEAST ONE STILL-EXISTING group. Counting
            // total-minus-ungrouped overcounted when a group was deleted but its id
            // lingered on contacts (orphaned tag) — that made "grouped" read e.g. 5
            // while the live group count was 0. Intersect against live group ids so
            // the two agree.
            'grouped_total'  => $allContacts->filter(function ($c) use ($groups) {
                $ids = is_array($c->contact_group) ? array_map('strval', $c->contact_group) : [];
                return count(array_intersect($ids, $groups->pluck('id')->map(fn ($id) => (string) $id)->all())) > 0;
            })->count(),
        ];

        // Per-group member counts (encrypted column → compute in PHP).
        $groupsWithCounts = ContactGroup::query()->tap($scopeGroup)->orderBy('id', 'desc')->get()->map(function ($g) use ($allContacts) {
            $g->members_count = $allContacts->filter(function ($c) use ($g) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                return in_array((string) $g->id, array_map('strval', $list), true);
            })->count();
            return $g;
        });

        $groupCounts = $groupsWithCounts->pluck('members_count', 'id');

        // Workspace tags + how many contacts carry each — powers the Tags
        // filter chips and the bulk-tag picker.
        $wsIdForTags = (int) ($request->user()->current_workspace_id ?? 0);
        $tags = $wsIdForTags
            ? \App\Models\Tag::where('workspace_id', $wsIdForTags)->orderBy('name')->get()
            : collect();
        $tagCounts = $allContacts->flatMap(fn ($c) => $c->tags->pluck('id'))->countBy();

        return view('user.contacts.index', compact('contacts', 'groups', 'groupsWithCounts', 'groupCounts', 'stats', 'searchQ', 'groupSel', 'tags', 'tagCounts', 'tagSel', 'perPage', 'allowedPerPage'));
    }

    /**
     * Serialize a contact for AJAX/JSON responses, including its decrypted
     * group models so the frontend can render row pills directly.
     */
    protected function serializeContact(Contact $contact): array
    {
        $groupIds = is_array($contact->contact_group) ? $contact->contact_group : [];
        $groupModels = [];
        if (!empty($groupIds)) {
            $intIds = array_map('intval', $groupIds);
            $groupModels = ContactGroup::whereIn('id', $intIds)->get()->map(function ($g) {
                return [
                    'id'    => $g->id,
                    'name'  => $g->user_group,
                    'color' => $g->color,
                ];
            })->values()->all();
        }

        return [
            'id'           => $contact->id,
            'name'         => $contact->name,
            'first_name'   => $contact->first_name,
            'middle_name'  => $contact->middle_name,
            'last_name'    => $contact->last_name,
            'title'        => $contact->title,
            'mobile'       => $contact->mobile,
            // Masked form for table display; raw `mobile` above stays for the
            // edit-modal prefill. Keeps AJAX-rendered rows consistent with the
            // server-rendered mask_phone() cells.
            'mobile_masked' => mask_phone($contact->mobile),
            'country_code' => $contact->country_code,
            'email'        => $contact->email,
            'language'     => $contact->language,
            'address'      => $contact->address,
            'msg'          => $contact->msg,
            'group_ids'    => array_map('strval', $groupIds),
            'groups'       => $groupModels,
            // Status / source have NO DB columns — they live in custom_attributes.
            // Surfaced here so AJAX-rebuilt rows can prefill the edit modal.
            'status'       => (string) (is_array($contact->custom_attributes) ? ($contact->custom_attributes['status'] ?? '') : ''),
            'source'       => (string) (is_array($contact->custom_attributes) ? ($contact->custom_attributes['source'] ?? '') : ''),
        ];
    }

    public function store(Request $request)
    {
        // Plan limit — block adding contacts beyond the package cap.
        $wsId = (int) ($request->user()?->current_workspace_id ?? 0);
        \App\Services\PlanLimitGuard::check(
            $request->user()->currentWorkspace,
            'contacts_limit',
            \App\Models\Contact::where('workspace_id', $wsId)->count(),
        );

        $validated = $request->validate([
            'title'           => 'nullable|string|max:50',
            'first_name'      => 'required|string|max:191',
            'middle_name'     => 'nullable|string|max:191',
            'last_name'       => 'nullable|string|max:191',
            'name'            => 'nullable|string|max:191',
            'contact_group'   => 'nullable|array',
            'contact_group.*' => 'integer|exists:contact_groups,id',
            'country_code'    => 'nullable|string|max:10',
            'mobile'          => 'required|string|max:32',
            'email'           => 'nullable|email|max:191',
            'language'        => 'nullable|string|max:80',
            'address'         => 'nullable|string|max:1000',
            'msg'             => 'nullable|string|max:2000',
            'image'           => 'nullable|image|max:2048',
            'is_unsubscribed' => 'sometimes|boolean',
            // Status (Active / Prospect / Customer / VIP) + Source. The contacts
            // table has NO status column — both live in custom_attributes, the
            // same mapping the CSV import uses for its "apply to every row" knobs.
            'status'          => 'nullable|string|max:64',
            'source'          => 'nullable|string|max:128',
        ]);

        $fullName = $request->input('name')
            ?: trim(implode(' ', array_filter([
                $request->title,
                $request->first_name,
                $request->middle_name,
                $request->last_name,
            ])));

        $custom = [];
        if (($s = trim((string) $request->input('status', ''))) !== '')  $custom['status'] = $s;
        if (($v = trim((string) $request->input('source', ''))) !== '')  $custom['source'] = $v;

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('contacts', media_disk());
        }

        $mobile = $request->mobile;
        if ($request->country_code && !str_starts_with((string) $mobile, $request->country_code)) {
            $mobile = $request->country_code . ' ' . $mobile;
        }

        $contact = Contact::create([
            'user_id'         => $request->user()?->id,
            'workspace_id'    => $wsId,
            'title'           => $request->title,
            'first_name'      => $request->first_name,
            'middle_name'     => $request->middle_name,
            'last_name'       => $request->last_name,
            'name'            => $fullName,
            'language'        => $request->language,
            'address'         => $request->address,
            'contact_group'   => array_map('strval', $request->input('contact_group', [])),
            'email'           => $request->email,
            'country_code'    => $request->country_code,
            'mobile'          => $mobile,
            'msg'             => $request->msg,
            'image'           => $imagePath,
            'is_unsubscribed' => (bool) $request->boolean('is_unsubscribed'),
            'custom_attributes' => $custom,
        ]);

        // Webhook: contact_created. Fired here on the single-contact create
        // path (NOT a global observer) so a bulk CSV import can't trigger a
        // webhook storm — same policy as contact_updated below.
        \App\Services\WebhookService::emit('contact_created', [
            'workspace_id' => $contact->workspace_id,
            'user_id'      => $contact->user_id,
            'contact_id'   => $contact->id,
            'name'         => $contact->name,
            'phone_number' => preg_replace('/\D+/', '', (string) $contact->mobile) ?: null,
            'email'        => $contact->email,
            'timestamp'    => now()->timestamp,
        ], $contact->user_id);

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Contact added.',
                'contact' => $this->serializeContact($contact),
            ]);
        }

        return redirect()->route('user.contacts')->with('status', 'Contact added.');
    }

    public function update(Request $request, $id)
    {
        $contact = $this->findContactInCurrentWorkspace($request, (int) $id);

        $validated = $request->validate([
            'title'           => 'nullable|string|max:50',
            'first_name'      => 'nullable|string|max:191',
            'middle_name'     => 'nullable|string|max:191',
            'last_name'       => 'nullable|string|max:191',
            'name'            => 'nullable|string|max:191',
            'contact_group'   => 'nullable|array',
            'contact_group.*' => 'integer|exists:contact_groups,id',
            'country_code'    => 'nullable|string|max:10',
            'mobile'          => 'nullable|string|max:32',
            'email'           => 'nullable|email|max:191',
            'language'        => 'nullable|string|max:80',
            'address'         => 'nullable|string|max:1000',
            'msg'             => 'nullable|string|max:2000',
            'image'           => 'nullable|image|max:2048',
            'is_unsubscribed' => 'sometimes|boolean',
            'status'          => 'nullable|string|max:64',
            'source'          => 'nullable|string|max:128',
        ]);

        $fullName = $request->input('name')
            ?: trim(implode(' ', array_filter([
                $request->title,
                $request->first_name,
                $request->middle_name,
                $request->last_name,
            ])));

        if ($request->hasFile('image')) {
            $contact->image = $request->file('image')->store('contacts', media_disk());
        }

        $contact->title         = $request->title ?? $contact->title;
        $contact->first_name    = $request->first_name ?? $contact->first_name;
        $contact->middle_name   = $request->middle_name ?? $contact->middle_name;
        $contact->last_name     = $request->last_name ?? $contact->last_name;
        $contact->name          = $fullName ?: $contact->name;
        $contact->language      = $request->language ?? $contact->language;
        $contact->address       = $request->address ?? $contact->address;
        if ($request->has('contact_group')) {
            $contact->contact_group = array_map('strval', $request->input('contact_group', []));
        }
        $contact->email         = $request->email ?? $contact->email;
        $contact->country_code  = $request->country_code ?? $contact->country_code;
        if ($request->mobile) {
            $mobile = $request->mobile;
            if ($request->country_code && !str_starts_with((string) $mobile, $request->country_code)) {
                $mobile = $request->country_code . ' ' . $mobile;
            }
            $contact->mobile = $mobile;
        }
        $contact->msg = $request->msg ?? $contact->msg;
        // Status / source live in custom_attributes (no dedicated columns).
        // Only touch them when the field was submitted; an empty value clears it.
        foreach (['status', 'source'] as $ca) {
            if (! $request->has($ca)) continue;
            $bag = is_array($contact->custom_attributes) ? $contact->custom_attributes : [];
            $val = trim((string) $request->input($ca, ''));
            if ($val === '') { unset($bag[$ca]); } else { $bag[$ca] = $val; }
            $contact->custom_attributes = $bag;
        }
        if ($request->has('is_unsubscribed')) {
            $contact->is_unsubscribed = $request->boolean('is_unsubscribed');
        }
        $optInChanged = $contact->isDirty('is_unsubscribed');
        $anyChange    = $contact->isDirty();
        $contact->save();

        // Webhook: contact_updated (any field) + contact_opt_in (subscribe/
        // unsubscribe toggle). Fired here on the single-contact edit path —
        // deliberately NOT a global Contact observer so a bulk CSV import
        // can't trigger a webhook storm.
        if ($anyChange) {
            \App\Services\WebhookService::emit('contact_updated', [
                'workspace_id' => $contact->workspace_id,
                'user_id'      => $contact->user_id,
                'contact_id'   => $contact->id,
                'name'         => $contact->name,
                'phone_number' => preg_replace('/\D+/', '', (string) $contact->mobile) ?: null,
                'email'        => $contact->email,
                'timestamp'    => now()->timestamp,
            ], $contact->user_id);
        }
        if ($optInChanged) {
            \App\Services\WebhookService::emit('contact_opt_in', [
                'workspace_id' => $contact->workspace_id,
                'user_id'      => $contact->user_id,
                'contact_id'   => $contact->id,
                'phone_number' => preg_replace('/\D+/', '', (string) $contact->mobile) ?: null,
                'opted_in'     => !$contact->is_unsubscribed,
                'action'       => $contact->is_unsubscribed ? 'unsubscribed' : 'resubscribed',
                'source'       => 'manual',
                'timestamp'    => now()->timestamp,
            ], $contact->user_id);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Contact updated.',
                'contact' => $this->serializeContact($contact),
            ]);
        }

        return redirect()->route('user.contacts')->with('status', 'Contact updated.');
    }

    public function destroy(Request $request, $id)
    {
        $contact = $this->findContactInCurrentWorkspace($request, (int) $id);
        $contact->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Contact deleted.',
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.contacts')->with('status', 'Contact deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:contacts,id',
        ]);

        $ids = $request->input('ids', []);
        // Critical: constrain the delete to the caller's workspace.
        // Otherwise any authed user could delete any contact by id.
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);
        $userId = (int) ($request->user()?->id ?? 0);
        $deleted = Contact::query()
            ->whereIn('id', $ids)
            ->where(function ($q) use ($wsId, $userId) {
                $q->where('workspace_id', $wsId)
                  ->orWhere(function ($qq) use ($userId) { $qq->whereNull('workspace_id')->where('user_id', $userId); });
            })
            ->delete();

        $count = (int) $deleted;
        $message = "Deleted {$count} contact" . ($count === 1 ? '' : 's') . '.';

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => $message,
                'ids'     => array_map('intval', $ids),
            ]);
        }

        return redirect()->route('user.contacts')->with('status', $message);
    }

    /**
     * Column-name aliases — sane defaults so a CSV with "Phone Number"
     * (or "telefono", "whatsapp") still maps to the `mobile` field.
     * Header strings are lowercased + stripped of non-alphanumerics
     * before lookup.
     */
    private const HEADER_ALIASES = [
        'name'        => ['name', 'fullname', 'contactname', 'firstname', 'first_name'],
        'last_name'   => ['lastname', 'last_name', 'surname', 'family_name', 'familyname'],
        'mobile'      => ['mobile', 'phone', 'phonenumber', 'phone_number', 'whatsapp', 'whatsappnumber', 'number', 'tel', 'telephone', 'cell', 'cellphone', 'msisdn'],
        'email'       => ['email', 'emailaddress', 'mail'],
        'country_code'=> ['countrycode', 'country_code', 'cc', 'dialcode'],
        'group'       => ['group', 'groups', 'contactgroup', 'contact_group', 'list', 'lists'],
        'language'    => ['language', 'lang', 'locale'],
    ];

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel|max:5120',
        ]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (!$handle) return response()->json(['ok' => false, 'message' => 'Could not read file.'], 422);

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return response()->json(['ok' => false, 'message' => 'Empty file.'], 422);
        }

        $detected = $this->detectColumns($headers);
        $rows = [];
        for ($i = 0; $i < 5; $i++) {
            $row = fgetcsv($handle);
            if ($row === false) break;
            $rows[] = array_combine(
                array_pad($headers, count($row), ''),
                array_pad($row, count($headers), '')
            ) ?: [];
        }
        fclose($handle);

        return response()->json([
            'ok'       => true,
            'headers'  => array_map(fn ($h) => trim((string) $h), $headers),
            'detected' => $detected,
            'rows'     => $rows,
        ]);
    }

    /**
     * #4 — Downloadable sample CSV. Header row uses the canonical column
     * names the smart-detector matches against, plus two example rows so
     * the operator can see the expected shape (country_code split out,
     * comma-separated groups in one cell).
     */
    public function sampleCsv(): \Symfony\Component\HttpFoundation\Response
    {
        $csv = implode("\n", [
            'name,last_name,country_code,mobile,email,group,language',
            'Aarav Sharma,Sharma,91,9812345678,aarav@example.com,"VIP, New leads",en',
            'Priya Patel,Patel,91,9898765432,priya@example.com,Customers,en',
        ]) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contacts-sample.csv"',
        ]);
    }

    public function import(Request $request)
    {
        // Plan: cap on contacts (best-effort — actual import may add many
        // rows; we block if already at limit; per-row checks happen in
        // the inner loop if you want them tighter).
        $wsId = (int) ($request->user()?->current_workspace_id ?? 0);
        \App\Services\PlanLimitGuard::check(
            $request->user()->currentWorkspace,
            'contacts_limit',
            \App\Models\Contact::where('workspace_id', $wsId)->count(),
        );

        $request->validate([
            // 50 MB — a 50k-row CSV is ~5–8 MB, so the old 5 MB cap silently
            // rejected big imports (the 422 wasn't surfaced in the UI). 50 MB
            // comfortably covers ~500k rows.
            'file'   => 'required|file|mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel|max:51200',
            // All four "apply to ALL rows" knobs are optional. We don't
            // restrict status to an enum here — the contacts table has
            // no status column; we map it onto custom_attributes instead
            // so existing campaigns can filter on it.
            'status'       => 'nullable|string|max:64',
            'source'       => 'nullable|string|max:128',
            'group_id'     => 'nullable|integer',
            'tag_ids'      => 'nullable|array',
            'tag_ids.*'    => 'integer',
        ]);

        // Large imports can legitimately run a while — lift the time limit for
        // this request (the server already allows it; this guards stricter
        // php.ini setups so a big file never dies mid-loop).
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->with('status', 'Could not read uploaded file.');
        }

        $userId     = $request->user()?->id;
        $imported   = 0;
        $skipped    = 0;
        $duplicates = 0;
        $headers    = null;
        $colMap     = [];

        // Pre-resolve the apply-to-all defaults so we don't hit the DB
        // once per row.
        $globalGroupId = $request->integer('group_id') ?: null;
        $globalStatus  = (string) $request->string('status')->toString();
        $globalSource  = (string) $request->string('source')->toString();
        // Bonus apply: build the contact_group JSON value once.
        $globalGroupIds = $globalGroupId ? [(string) $globalGroupId] : [];

        // De-dup: re-importing the same CSV must NOT create duplicate
        // contacts. mobile/country_code are encrypted (no SQL match
        // possible) and stored inconsistently — single-add prepends the
        // country code into `mobile`, import keeps them separate. So we
        // canonicalise BOTH sides to "<cc><digits>" and compare on that.
        // Build the existing-phone set once up front.
        $canon = fn (?string $cc, ?string $mobile): string => $this->canonPhone($cc, $mobile);
        // Existing-phone dedup set — chunked so a workspace that already has
        // hundreds of thousands of contacts doesn't load them all into memory.
        $existingPhones = [];
        Contact::where('workspace_id', $wsId)
            ->select(['id', 'country_code', 'mobile'])
            ->chunkById(5000, function ($rows) use (&$existingPhones, $canon) {
                foreach ($rows as $c) {
                    $key = $canon($c->country_code, $c->mobile);
                    if ($key !== '') $existingPhones[$key] = true;
                }
            });

        // Pre-resolve groups ONCE into a name→id cache. Previously this ran a
        // full-table ContactGroup fetch on EVERY row (O(rows × groups)) — the
        // main reason a 50k import crawled. Missing groups are created on first
        // sight and cached, so each name is created at most once.
        $groupCache = [];
        ContactGroup::query()
            ->where(function ($q) use ($wsId, $userId) {
                $q->where('workspace_id', $wsId)
                  ->orWhere(function ($qq) use ($userId) { $qq->whereNull('workspace_id')->where('user_id', $userId); });
            })
            ->get(['id', 'user_group'])
            ->each(function ($g) use (&$groupCache) {
                $groupCache[mb_strtolower((string) $g->user_group)] = (string) $g->id;
            });
        $resolveGroup = function (string $gName) use (&$groupCache, $wsId, $userId): string {
            $key = mb_strtolower($gName);
            if (isset($groupCache[$key])) return $groupCache[$key];
            $g = ContactGroup::create(['user_id' => $userId, 'workspace_id' => $wsId, 'user_group' => $gName]);
            return $groupCache[$key] = (string) $g->id;
        };

        // Insert with model EVENTS DISABLED. A bulk import must NOT fire the
        // per-row Contact::created hook (FlowEnrollmentService) — that would be
        // slow AND could blast a flow message to every imported contact. Rows
        // are committed in batches so 50k+ inserts don't run as 50k separate
        // autocommits (the other big slowdown).
        Contact::withoutEvents(function () use (
            $handle, &$headers, &$colMap, &$imported, &$skipped, &$duplicates,
            &$existingPhones, $resolveGroup, $globalGroupIds, $globalStatus,
            $globalSource, $userId, $wsId, $canon
        ) {
            $batch = 0;
            \DB::beginTransaction();
            while (($row = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = $row;
                    $colMap  = $this->detectColumns($headers);
                    continue;
                }

                $get = fn (string $key) => isset($colMap[$key]) && isset($row[$colMap[$key]])
                    ? trim((string) $row[$colMap[$key]])
                    : '';

                $name     = $get('name');
                $lastName = $get('last_name');
                $mobile   = $get('mobile');
                $email    = $get('email') ?: null;
                $cc       = $get('country_code') ?: null;
                $language = $get('language') ?: null;
                $groupRaw = $get('group');

                if ($name === '' || $mobile === '') { $skipped++; continue; }

                // De-dup against the workspace + earlier rows in this file.
                $phoneKey = $canon($cc, $mobile);
                if ($phoneKey !== '' && isset($existingPhones[$phoneKey])) { $duplicates++; continue; }
                if ($phoneKey !== '') $existingPhones[$phoneKey] = true;

                // Per-row groups via the O(1) cache (no DB hit unless a
                // brand-new group name appears); global group merged in.
                $groupIds = [];
                if ($groupRaw !== '') {
                    foreach (preg_split('/\s*,\s*/', $groupRaw) as $gName) {
                        if ($gName === '') continue;
                        $groupIds[] = $resolveGroup($gName);
                    }
                }
                foreach ($globalGroupIds as $gId) {
                    if (!in_array($gId, $groupIds, true)) $groupIds[] = $gId;
                }

                $custom = [];
                if ($globalStatus !== '') $custom['status'] = $globalStatus;
                if ($globalSource !== '') $custom['source'] = $globalSource;

                Contact::create([
                    'user_id'           => $userId,
                    'workspace_id'      => $wsId,
                    'name'              => $name,
                    'first_name'        => $name,
                    'last_name'         => $lastName ?: null,
                    'mobile'            => $mobile,
                    'email'             => $email,
                    'country_code'      => $cc,
                    'language'          => $language,
                    'contact_group'     => $groupIds,
                    'custom_attributes' => $custom ?: null,
                ]);
                $imported++;

                if (++$batch >= 1000) { \DB::commit(); \DB::beginTransaction(); $batch = 0; }
            }
            \DB::commit();
        });
        fclose($handle);

        $message = "Imported {$imported} contacts";
        $notes = [];
        if ($duplicates > 0) $notes[] = "{$duplicates} duplicate" . ($duplicates === 1 ? '' : 's') . " skipped";
        if ($skipped > 0)    $notes[] = "{$skipped} invalid row" . ($skipped === 1 ? '' : 's') . " skipped";
        if ($notes) $message .= ' (' . implode(', ', $notes) . ')';
        $message .= '.';

        if ($request->wantsJson()) {
            return response()->json([
                'ok'         => true,
                'message'    => $message,
                'imported'   => $imported,
                'skipped'    => $skipped,
                'duplicates' => $duplicates,
            ]);
        }

        return redirect()->route('user.contacts')->with('status', $message);
    }

    /**
     * Match CSV header strings against HEADER_ALIASES. Strings are
     * normalised (lowercase, drop non-alphanumerics) before lookup so
     * "Phone Number" and "phone_number" both map to `mobile`.
     *
     * @return array<string,int>  field-key → column-index
     */
    private function detectColumns(array $headers): array
    {
        $map = [];
        $normalize = fn (string $h) => preg_replace('/[^a-z0-9]/', '', strtolower(trim($h)));
        foreach ($headers as $i => $raw) {
            $norm = $normalize((string) $raw);
            if ($norm === '') continue;
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (isset($map[$field])) continue;   // first match wins
                if (in_array($norm, $aliases, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }
        return $map;
    }

    // -----------------------------------------------------------------
    // Bulk actions on the selected contacts (Export / Add-to-group /
    // Remove-from-group) + a one-click duplicate cleanup. All are
    // workspace-scoped so a forged id can't touch another tenant's rows.
    // -----------------------------------------------------------------

    /**
     * Export the selected contacts to CSV. Columns match the import
     * sample so an export can be re-imported cleanly. Leading =,+,-,@ are
     * neutralised to block spreadsheet formula injection.
     */
    public function bulkExport(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        // Two modes:
        //   • all=1  → export EVERY contact that matches the current list view
        //     (group / tag / search), NOT just the 12 on-screen rows. The list
        //     paginates 12/page, so a plain multi-select could never reach the
        //     whole 1,800+ book — this is the "Export all" button.
        //   • else   → export exactly the selected ids (the bulk toolbar).
        $exportAll = $request->boolean('all');

        if ($exportAll) {
            // Encrypted name/mobile/email force the group/search/tag filters into
            // PHP, exactly like index(). Load the workspace book once, then filter.
            $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);
            $userId = (int) ($request->user()?->id ?? 0);
            $contacts = Contact::query()
                ->where(function ($qq) use ($wsId, $userId) {
                    $qq->where('workspace_id', $wsId)
                       ->orWhere(fn ($q) => $q->whereNull('workspace_id')->where('user_id', $userId));
                })
                ->with('tags')
                ->orderByDesc('created_at')
                ->get();

            $groupSel = trim((string) $request->input('group', ''));
            if ($groupSel === 'no_group') {
                $contacts = $contacts->filter(fn ($c) => empty($c->contact_group))->values();
            } elseif ($groupSel !== '' && $groupSel !== 'all' && ctype_digit($groupSel)) {
                $contacts = $contacts->filter(function ($c) use ($groupSel) {
                    $list = is_array($c->contact_group) ? $c->contact_group : [];
                    return in_array($groupSel, array_map('strval', $list), true);
                })->values();
            }
            $tagSel = trim((string) $request->input('tag', ''));
            if ($tagSel !== '' && ctype_digit($tagSel)) {
                $contacts = $contacts->filter(fn ($c) => $c->tags->contains('id', (int) $tagSel))->values();
            }
            $searchQ = trim((string) $request->input('q', ''));
            if ($searchQ !== '') {
                $contacts = $contacts->filter(function ($c) use ($searchQ) {
                    foreach (['name', 'first_name', 'last_name', 'mobile', 'email'] as $f) {
                        if (mb_stripos((string) ($c->{$f} ?? ''), $searchQ) !== false) return true;
                    }
                    return false;
                })->values();
            }
        } else {
            $request->validate([
                'ids'   => 'required|array',
                'ids.*' => 'integer',
            ]);

            $contacts = Contact::query()
                ->whereIn('id', $request->input('ids', []))
                ->where($this->workspaceScopeClause($request))
                ->orderByDesc('created_at')
                ->get();
        }

        $groupNames = ContactGroup::query()
            ->where($this->workspaceScopeClause($request))
            ->pluck('user_group', 'id');

        $safe = fn ($v) => (is_string($v) && $v !== '' && preg_match('/^[=+\-@]/', $v)) ? "'" . $v : $v;

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['name', 'last_name', 'country_code', 'mobile', 'email', 'group', 'language', 'memo']);
        foreach ($contacts as $c) {
            $ids   = is_array($c->contact_group) ? $c->contact_group : [];
            $names = collect($ids)->map(fn ($i) => $groupNames[(int) $i] ?? null)->filter()->implode(', ');
            fputcsv($out, array_map($safe, [
                (string) $c->name,
                (string) $c->last_name,
                (string) $c->country_code,
                (string) $c->mobile,
                (string) $c->email,
                $names,
                (string) $c->language,
                (string) $c->msg,
            ]));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contacts-export.csv"',
        ]);
    }

    /**
     * Add or remove a single group on every selected contact.
     */
    /**
     * Add or remove one tag across a whole selection.
     *
     * Deliberately mirrors attachTag()'s semantics rather than doing a blunt
     * sync(): the flow `tag_added` trigger fires ONLY for contacts that did
     * not already carry the tag, so bulk-tagging 200 contacts where 190 are
     * already tagged enrolls 10, not 200.
     */
    public function bulkTag(Request $request): JsonResponse
    {
        $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
            'tag_id' => 'nullable|integer',
            'name'   => 'nullable|string|max:80',
            'action' => 'required|in:add,remove',
        ]);

        $wsId = (int) ($request->user()->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 422);

        $action = $request->input('action');

        $tag = null;
        if ($request->filled('tag_id')) {
            $tag = \App\Models\Tag::where('id', $request->integer('tag_id'))
                ->where('workspace_id', $wsId)->first();
        }
        // Create-on-the-fly is add-only — "remove a tag that doesn't exist"
        // is a no-op, and silently minting a tag to then detach it is noise.
        if (!$tag && $action === 'add' && $request->filled('name')) {
            $name = trim((string) $request->input('name'));
            $slug = \Illuminate\Support\Str::slug($name) ?: \Illuminate\Support\Str::random(8);
            $tag  = \App\Models\Tag::firstOrCreate(
                ['workspace_id' => $wsId, 'slug' => $slug],
                ['name' => $name, 'color' => '#075E54'],
            );
        }
        if (!$tag) return response()->json(['ok' => false, 'error' => 'tag_required'], 422);

        $contacts = Contact::query()
            ->whereIn('id', $request->input('ids', []))
            ->where($this->workspaceScopeClause($request))
            ->get();

        $changed = 0;
        foreach ($contacts as $c) {
            $already = $c->tags()->where('tags.id', $tag->id)->exists();
            if ($action === 'add') {
                if ($already) continue;
                $c->tags()->syncWithoutDetaching([$tag->id => ['added_by' => $request->user()->id]]);
                $changed++;
                try { app(\App\Services\Flow\FlowEnrollmentService::class)->onTagAdded($c, (int) $tag->id); }
                catch (\Throwable $e) { \Log::warning('[CONTACT-TAG] bulk onTagAdded failed: ' . $e->getMessage()); }
            } else {
                if (!$already) continue;
                $c->tags()->detach($tag->id);
                $changed++;
            }
        }

        $verb = $action === 'add' ? 'tagged' : 'untagged';
        return response()->json([
            'ok'      => true,
            'message' => "{$changed} contact" . ($changed === 1 ? '' : 's') . " {$verb} \"{$tag->name}\".",
            'changed' => $changed,
            'tag'     => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color],
        ]);
    }

    /**
     * Resolve EVERY contact matching the active list filter (group / tag /
     * search) for a "select all matching" bulk action. Encrypted name / mobile
     * / email force the same in-PHP filtering index() uses, so this mirrors
     * that logic — one source of truth for "which contacts this filter selects".
     */
    private function filteredContactsForBulk(Request $request): \Illuminate\Support\Collection
    {
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);
        $userId = (int) ($request->user()?->id ?? 0);
        $contacts = Contact::query()
            ->where(function ($qq) use ($wsId, $userId) {
                $qq->where('workspace_id', $wsId)
                   ->orWhere(fn ($q) => $q->whereNull('workspace_id')->where('user_id', $userId));
            })
            ->with('tags')
            ->get();

        $groupSel = trim((string) $request->input('group', ''));
        if ($groupSel === 'no_group') {
            $contacts = $contacts->filter(fn ($c) => empty($c->contact_group))->values();
        } elseif ($groupSel !== '' && $groupSel !== 'all' && ctype_digit($groupSel)) {
            $contacts = $contacts->filter(function ($c) use ($groupSel) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                return in_array($groupSel, array_map('strval', $list), true);
            })->values();
        }
        $tagSel = trim((string) $request->input('tag', ''));
        if ($tagSel !== '' && ctype_digit($tagSel)) {
            $contacts = $contacts->filter(fn ($c) => $c->tags->contains('id', (int) $tagSel))->values();
        }
        $searchQ = trim((string) $request->input('q', ''));
        if ($searchQ !== '') {
            $contacts = $contacts->filter(function ($c) use ($searchQ) {
                foreach (['name', 'first_name', 'last_name', 'mobile', 'email'] as $f) {
                    if (mb_stripos((string) ($c->{$f} ?? ''), $searchQ) !== false) return true;
                }
                return false;
            })->values();
        }
        return $contacts->values();
    }

    public function bulkGroup(Request $request): JsonResponse
    {
        $request->validate([
            // ids OR all=1 (select-all-matching) — one of the two must be present.
            'ids'      => 'required_without:all|array',
            'ids.*'    => 'integer',
            'group_id' => 'required|integer',
            'action'   => 'required|in:add,remove',
            'all'      => 'nullable|boolean',
        ]);

        $gid    = (string) $request->integer('group_id');
        $action = $request->input('action');
        // Confirm the group is in the caller's workspace (throws 404 otherwise).
        $group  = $this->findGroupInCurrentWorkspace($request, (int) $gid);

        // "Select all matching" — operate on EVERY contact matching the active
        // list filter (group / tag / search), not just a page of ids. This is
        // what lets an operator drop all 3,500+ contacts into one group in a
        // single action instead of paging through the book 12 rows at a time.
        if ($request->boolean('all')) {
            $contacts = $this->filteredContactsForBulk($request);
        } else {
            $contacts = Contact::query()
                ->whereIn('id', $request->input('ids', []))
                ->where($this->workspaceScopeClause($request))
                ->get();
        }

        $changed = 0;
        foreach ($contacts as $c) {
            $list = array_map('strval', is_array($c->contact_group) ? $c->contact_group : []);
            if ($action === 'add') {
                if (!in_array($gid, $list, true)) {
                    $list[] = $gid;
                    $c->contact_group = array_values($list);
                    $c->save();
                    $changed++;
                }
            } else {
                $new = array_values(array_filter($list, fn ($x) => $x !== $gid));
                if (count($new) !== count($list)) {
                    $c->contact_group = $new;
                    $c->save();
                    $changed++;
                }
            }
        }

        $verb = $action === 'add' ? 'added to' : 'removed from';
        return response()->json([
            'ok'      => true,
            'message' => "{$changed} contact" . ($changed === 1 ? '' : 's') . " {$verb} \"{$group->user_group}\".",
            'changed' => $changed,
        ]);
    }

    /**
     * Collapse duplicate phone numbers in this workspace, keeping the
     * earliest-created row for each number. Solves the "imported the same
     * file 3× → every contact tripled" mess. Blank-phone rows are left
     * untouched (no reliable identity to dedupe on).
     */
    public function dedupe(Request $request): JsonResponse
    {
        $contacts = Contact::query()
            ->where($this->workspaceScopeClause($request))
            ->orderBy('id') // earliest id wins
            ->get(['id', 'country_code', 'mobile']);

        $seen      = [];
        $removeIds = [];
        foreach ($contacts as $c) {
            $key = $this->canonPhone($c->country_code, $c->mobile);
            if ($key === '') continue;
            if (isset($seen[$key])) {
                $removeIds[] = $c->id;
            } else {
                $seen[$key] = $c->id;
            }
        }

        $removed = 0;
        if (!empty($removeIds)) {
            $removed = (int) Contact::query()
                ->whereIn('id', $removeIds)
                ->where($this->workspaceScopeClause($request))
                ->delete();
        }

        return response()->json([
            'ok'      => true,
            'message' => $removed > 0
                ? "Removed {$removed} duplicate contact" . ($removed === 1 ? '' : 's') . ", keeping the earliest of each number."
                : 'No duplicate phone numbers found.',
            'removed' => $removed,
        ]);
    }

    // -----------------------------------------------------------------
    // Contact groups (prefixed with "group" to avoid collisions)
    // -----------------------------------------------------------------

    public function groupIndex(): View
    {
        return $this->index(request());
    }

    public function groupStore(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:191',
            'note'  => 'nullable|string|max:500',
            'color' => 'nullable|string|max:32',
        ]);

        $group = ContactGroup::create([
            'user_id'      => $request->user()?->id,
            'workspace_id' => $request->user()?->current_workspace_id,
            'user_group'   => $request->input('name'),
            'note'         => $request->input('note'),
            'color'        => $request->input('color'),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Group created.',
                'group'   => [
                    'id'            => $group->id,
                    'name'          => $group->user_group,
                    'note'          => $group->note,
                    'color'         => $group->color,
                    'members_count' => 0,
                ],
            ]);
        }

        return back()->with('status', 'Group created.');
    }

    public function groupUpdate(Request $request, $id)
    {
        $request->validate([
            'name'  => 'required|string|max:191',
            'note'  => 'nullable|string|max:500',
            'color' => 'nullable|string|max:32',
        ]);

        $group = $this->findGroupInCurrentWorkspace($request, (int) $id);
        $group->user_group = $request->input('name');
        $group->note       = $request->input('note');
        $group->color      = $request->input('color') ?? $group->color;
        $group->save();

        if ($request->wantsJson()) {
            // Compute members count — scoped to current workspace so a
            // group's "members" pill doesn't count contacts from
            // another workspace that happen to reference the id.
            $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);
            $userId = (int) ($request->user()?->id ?? 0);
            $membersCount = Contact::query()
                ->where(function ($q) use ($wsId, $userId) {
                    $q->where('workspace_id', $wsId)
                      ->orWhere(function ($qq) use ($userId) { $qq->whereNull('workspace_id')->where('user_id', $userId); });
                })
                ->get(['contact_group'])->filter(function ($c) use ($group) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                return in_array((string) $group->id, array_map('strval', $list), true);
            })->count();

            return response()->json([
                'ok'      => true,
                'message' => 'Group updated.',
                'group'   => [
                    'id'            => $group->id,
                    'name'          => $group->user_group,
                    'note'          => $group->note,
                    'color'         => $group->color,
                    'members_count' => $membersCount,
                ],
            ]);
        }

        return back()->with('status', 'Group updated.');
    }

    public function groupDestroy(Request $request, $id)
    {
        $group = $this->findGroupInCurrentWorkspace($request, (int) $id);
        $name  = $group->user_group;

        // Detach this group from any contact JSON arrays — scoped to
        // the current workspace so we don't scan/mutate contacts in
        // other tenants. (Pre-migration rows fall back to user-scope.)
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);
        $userId = (int) ($request->user()?->id ?? 0);
        Contact::query()
            ->where(function ($q) use ($wsId, $userId) {
                $q->where('workspace_id', $wsId)
                  ->orWhere(function ($qq) use ($userId) { $qq->whereNull('workspace_id')->where('user_id', $userId); });
            })
            ->get(['id', 'contact_group'])->each(function ($contact) use ($id) {
                $list = is_array($contact->contact_group) ? $contact->contact_group : [];
                $filtered = array_values(array_filter($list, fn ($g) => (string) $g !== (string) $id));
                if ($filtered !== $list) {
                    $contact->contact_group = $filtered;
                    $contact->save();
                }
            });

        $group->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Group deleted.',
                'id'      => (int) $id,
            ]);
        }

        return back()->with('status', 'Contact group "' . $name . '" deleted.');
    }
}

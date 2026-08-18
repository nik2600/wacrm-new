<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WaTemplate;
use App\Services\WorkspaceEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile-app WhatsApp message templates (B3 · TEMPLATES).
 *
 * Response shapes are kept byte-compatible with the existing Flutter app
 * (list: {templates:[...]}; categories: {categories:[{id,name}]}; create/
 * update/delete: {success, message, ...}), but the implementation runs
 * against OUR current model — App\Models\WaTemplate (the Meta-approved
 * WhatsApp Business templates), encrypted-at-rest, workspace-scoped.
 *
 * Multi-tenancy: every query is scoped to the authenticated Sanctum user's
 * workspace. Reads use WaTemplate::forCurrentWorkspace() (which also exposes
 * admin-seeded globals + legacy user-owned rows). Mutations stamp the row
 * with workspace_id + user_id and look the row up the same scope so an
 * operator can never touch another tenant's template by guessing an id.
 *
 * Field mapping vs the old TemplatesWasnap model (see the report at the
 * bottom of the batch):
 *   - old `category_id` (1=Marketing/2=Authentication/3=Utility) maps onto
 *     our `meta_category` (marketing|authentication|utility). We also keep
 *     our own industry `category` column. The list/detail payloads expose
 *     BOTH `category_id` (numeric, derived) and `category`/`meta_category`.
 *   - old `attachment_file` was a bare filename rendered to a full URL by
 *     the API. Our column stores a public-disk relative path; we expose the
 *     full URL as `attachment_file` (matching the app) and keep the raw
 *     stored path in `attachment_file_path`.
 *   - old free `buttons`/`quick_replies`/`carousel_data` JSON columns are
 *     folded into our single encrypted `buttons` + `carousel_data` arrays.
 */
class TemplateController extends Controller
{
    /** Old numeric category id ←→ our meta_category slug. */
    private const CATEGORY_ID_TO_META = [
        1 => 'marketing',
        2 => 'authentication',
        3 => 'utility',
    ];

    private const META_TO_CATEGORY_ID = [
        'marketing'      => 1,
        'authentication' => 2,
        'utility'        => 3,
    ];

    // -----------------------------------------------------------------
    // GET /get-templates  — list templates for the current workspace.
    // Contract: WhatsAppMessageApiController::getTemplates → {templates:[...]}
    // -----------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        try {
            $templates = WaTemplate::query()
                ->forCurrentWorkspace()
                ->orderByDesc('id')
                ->get()
                ->map(fn (WaTemplate $t) => $this->present($t))
                ->values();

            return response()->json([
                'success'   => true,
                'templates' => $templates,
            ], 200);
        } catch (\Throwable $e) {
            return $this->fail($e, 'fetching templates');
        }
    }

    // -----------------------------------------------------------------
    // GET /get-templates-category  — Meta category list.
    // Contract: WhatsAppMessageApiController::getCategories → {categories:[...]}
    // -----------------------------------------------------------------
    public function categories(): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'categories' => [
                ['id' => 1, 'name' => 'Marketing'],
                ['id' => 2, 'name' => 'Authentication'],
                ['id' => 3, 'name' => 'Utility'],
            ],
        ], 200);
    }

    // -----------------------------------------------------------------
    // GET /templates/{id}  — single template detail.
    // Contract: TemplateApiController::show → {success, message, data}
    // -----------------------------------------------------------------
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $template = WaTemplate::query()->forCurrentWorkspace()->find($id);
            if (! $template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found or unauthorized',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Template retrieved successfully',
                'data'    => $this->present($template),
            ], 200);
        } catch (\Throwable $e) {
            return $this->fail($e, 'retrieving template');
        }
    }

    // -----------------------------------------------------------------
    // POST /templates-store  — create a template.
    // Contract: TemplateApiController::store → {success, message, platform_type}
    // -----------------------------------------------------------------
    public function store(Request $request): JsonResponse
    {
        // Auto-decode JSON-string array fields (the app may send either a
        // real array or a JSON string for these).
        $this->normalizeJsonArrays($request, ['buttons', 'quick_replies', 'carousel_data', 'header_location']);

        $validator = Validator::make($request->all(), [
            'template_name'   => 'required|string|max:191',
            'template_type'   => 'nullable|in:standard,carousel,media,auth',
            'category'        => 'required',
            'header'          => 'nullable|string|max:255',
            'template_body'   => 'required_if:template_type,standard|nullable|string|max:4096',
            'footer'          => 'nullable|string|max:255',
            // Authentication (OTP) code validity — Meta allows 1–90 min; NULL → 10.
            'code_expiration_minutes' => 'nullable|integer|min:1|max:90',
            'language'        => 'nullable|string|max:16',
            'attachment_file' => 'nullable|file|max:16384',
            'buttons'         => 'nullable',
            'quick_replies'   => 'nullable',
            'carousel_data'   => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $wsId = (int) ($user->current_workspace_id ?? 0);

            $data = [
                'user_id'       => $user->id,
                'workspace_id'  => $wsId,
                'template_name' => $request->input('template_name'),
                'template_type' => $request->input('template_type', 'standard'),
                'header'        => $request->input('header'),
                'template_body' => (string) $request->input('template_body', ''),
                'footer'        => $request->input('footer'),
                'code_expiration_minutes' => $request->filled('code_expiration_minutes') ? (int) $request->input('code_expiration_minutes') : null,
                'language'      => $request->input('language', 'en_US'),
                // Industry bucket — our UI groups by this; default to utility.
                'category'      => $this->resolveCategory($request->input('category')),
                // Meta classification — derived from old numeric category id.
                'meta_category' => $this->resolveMetaCategory($request->input('category')),
                // Local approval state. Baileys workspaces auto-approve;
                // we mirror that here so the template is immediately sendable.
                'status'        => 'approved',
                'meta_status'   => 'APPROVED',
                'approved_at'   => now(),
            ];

            // Buttons: fold CTA + quick replies into our single buttons[] array.
            $buttons = $this->collectButtons($request);
            if (! empty($buttons)) {
                $data['buttons'] = $buttons;
            }

            // LOCATION header — {latitude, longitude, name, address}. The web
            // editor saves attachment_type='location' alongside header_location
            // so both engines know to render a pin (WABA TemplatePayloadBuilder
            // dispatches on attachment_type='LOCATION'; Unofficial API reads
            // header_location). Without that tag the send path silently drops
            // the pin. App parity: tag attachment_type when either the app
            // sends attachment_type='location' OR posts valid coordinates.
            $loc = $this->collectLocation($request);
            $wantsLocation = $loc !== null
                || strtolower((string) $request->input('attachment_type', '')) === 'location';
            if ($wantsLocation) {
                if ($loc === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation error',
                        'errors'  => ['latitude' => ['Enter a real latitude (-90 to 90) and longitude (-180 to 180).']],
                    ], 422);
                }
                $data['header_location'] = $loc;
                $data['attachment_type'] = 'location';
                $data['attachment_file'] = null; // location mode carries no file
            }

            // Carousel payload (already array via normalizeJsonArrays).
            if ($request->filled('carousel_data')) {
                $carousel = $request->input('carousel_data');
                if (is_array($carousel)) {
                    $data['carousel_data'] = $carousel;
                }
            }

            // Attachment upload → public disk (same path the web app uses).
            // Skip when location is the chosen header (mutually exclusive).
            if (! $wantsLocation && $request->hasFile('attachment_file')) {
                $file = $request->file('attachment_file');
                $data['attachment_type'] = $this->resolveAttachmentType($file);
                $data['attachment_file'] = $file->store('wa-templates', media_disk());
            }

            // Positional variable map from {var} / {{var}} tokens.
            $variableMap = $this->buildVariableMap(
                $request->input('header'),
                $request->input('template_body')
            );
            if ($variableMap) {
                $data['variable_map'] = $variableMap;
            }

            $template = WaTemplate::create($data);

            return response()->json([
                'success'       => true,
                'message'       => 'Template created successfully',
                'platform_type' => 'WA',
                'data'          => $this->present($template),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[WaDesk][API] Template creation error: ' . $e->getMessage());
            return $this->fail($e, 'creating template');
        }
    }

    // -----------------------------------------------------------------
    // PUT /templates/{id}  — update a template.
    // Contract: TemplateApiController::update → {success, message, platform_type}
    // -----------------------------------------------------------------
    public function update(Request $request, int $id): JsonResponse
    {
        $template = WaTemplate::query()->forCurrentWorkspace()->find($id);
        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found or unauthorized',
            ], 403);
        }

        $this->normalizeJsonArrays($request, ['buttons', 'quick_replies', 'carousel_data', 'header_location']);

        $validator = Validator::make($request->all(), [
            'template_name'   => 'required|string|max:191',
            'template_type'   => 'nullable|in:standard,carousel,media,auth',
            'category'        => 'required',
            'header'          => 'nullable|string|max:255',
            'template_body'   => 'required|string|max:4096',
            'footer'          => 'nullable|string|max:255',
            // Authentication (OTP) code validity — Meta allows 1–90 min; NULL → 10.
            'code_expiration_minutes' => 'nullable|integer|min:1|max:90',
            'language'        => 'nullable|string|max:16',
            'attachment_file' => 'nullable|file|max:16384',
            'buttons'         => 'nullable',
            'quick_replies'   => 'nullable',
            'carousel_data'   => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $data = [
                'template_name' => $request->input('template_name'),
                'template_type' => $request->input('template_type', $template->template_type),
                'header'        => $request->input('header'),
                'template_body' => (string) $request->input('template_body', ''),
                'footer'        => $request->input('footer'),
                'code_expiration_minutes' => $request->filled('code_expiration_minutes') ? (int) $request->input('code_expiration_minutes') : null,
                'language'      => $request->input('language', $template->language),
                'category'      => $this->resolveCategory($request->input('category')),
                'meta_category' => $this->resolveMetaCategory($request->input('category')),
            ];

            $buttons = $this->collectButtons($request);
            // Replace buttons only if the request carried button data at all.
            if ($request->has('buttons') || $request->has('button_type') || $request->has('quick_reply') || $request->has('quick_replies')) {
                $data['buttons'] = ! empty($buttons) ? $buttons : null;
            }

            // LOCATION header — replace only when the request carried location
            // data (so an unrelated edit doesn't wipe it). Tag attachment_type
            // so the send path can detect a LOCATION header (web parity — see
            // store()). An explicit attachment_type='none'/'image'/… in the
            // payload still wins (handled in the attachment branch below).
            $incomingAttachmentType = strtolower((string) $request->input('attachment_type', ''));
            $touchedLocation = $request->hasAny(['header_location', 'latitude', 'longitude'])
                || $incomingAttachmentType === 'location';
            $wantsLocation = false;
            if ($touchedLocation) {
                $loc = $this->collectLocation($request);
                if ($incomingAttachmentType === 'location' && $loc === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation error',
                        'errors'  => ['latitude' => ['Enter a real latitude (-90 to 90) and longitude (-180 to 180).']],
                    ], 422);
                }
                $data['header_location'] = $loc;
                if ($loc !== null) {
                    $data['attachment_type'] = 'location';
                    $data['attachment_file'] = null;
                    $wantsLocation = true;
                } elseif ($incomingAttachmentType !== '' && $incomingAttachmentType !== 'location') {
                    // User explicitly switched away from location — drop the tag.
                    $data['attachment_type'] = $incomingAttachmentType === 'none' ? null : $incomingAttachmentType;
                }
            }

            if ($request->filled('carousel_data')) {
                $carousel = $request->input('carousel_data');
                if (is_array($carousel)) {
                    $data['carousel_data'] = $carousel;
                }
            }

            // New attachment replaces the old one (old file removed from disk).
            // Skip when location is the chosen header (mutually exclusive).
            if (! $wantsLocation && $request->hasFile('attachment_file')) {
                if ($template->attachment_file) {
                    try {
                        media_storage()->delete($template->attachment_file);
                    } catch (\Throwable $e) { /* best-effort cleanup */ }
                }
                $file = $request->file('attachment_file');
                $data['attachment_type'] = $this->resolveAttachmentType($file);
                $data['attachment_file'] = $file->store('wa-templates', media_disk());
            }

            $variableMap = $this->buildVariableMap(
                $request->input('header'),
                $request->input('template_body')
            );
            $data['variable_map'] = $variableMap ?: null;

            $template->fill($data)->save();

            return response()->json([
                'success'       => true,
                'message'       => 'Template updated successfully',
                'platform_type' => 'WA',
                'data'          => $this->present($template->fresh()),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('[WaDesk][API] Template update error: ' . $e->getMessage());
            return $this->fail($e, 'updating template');
        }
    }

    // -----------------------------------------------------------------
    // DELETE /templates/{id}  AND  POST /templates/delete  — delete.
    // Contract: TemplateApiController::destroy → {success, message}
    // Supports a single {id} (route) or a bulk `ids[]` body.
    // -----------------------------------------------------------------
    public function destroy(Request $request, ?int $id = null): JsonResponse
    {
        try {
            $ids = $id !== null ? [$id] : array_map('intval', (array) $request->input('ids', []));
            // Single-id body fallback for POST /templates/delete.
            if (empty($ids) && $request->filled('id')) {
                $ids = [(int) $request->input('id')];
            }

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No template IDs provided.',
                ], 400);
            }

            // Workspace-scope the lookup so foreign-tenant ids never match.
            $templates = WaTemplate::query()
                ->forCurrentWorkspace()
                ->whereIn('id', $ids)
                ->get();

            if ($templates->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No matching templates found.',
                ], 404);
            }

            foreach ($templates as $template) {
                if ($template->attachment_file) {
                    try {
                        media_storage()->delete($template->attachment_file);
                    } catch (\Throwable $e) { /* best-effort cleanup */ }
                }
                // Remove carousel-card images too.
                if (is_array($template->carousel_data)) {
                    foreach ($template->carousel_data as $card) {
                        if (! empty($card['image'])) {
                            try {
                                media_storage()->delete($card['image']);
                            } catch (\Throwable $e) { /* best-effort cleanup */ }
                        }
                    }
                }
                $template->delete();
            }

            return response()->json([
                'success' => true,
                'message' => $templates->count() > 1
                    ? 'Templates deleted successfully'
                    : 'Template deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            return $this->fail($e, 'deleting template');
        }
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Shape a WaTemplate into the JSON the app expects. Mirrors the old
     * TemplatesWasnap row plus our extra columns. `attachment_file` is a
     * full URL (app reads it directly); raw stored path is preserved as
     * `attachment_file_path`.
     */
    private function present(WaTemplate $t): array
    {
        // Channel/engine sign so the app can badge each template:
        //   meta_template_id set    → Meta (WABA)       → code W
        //   twilio_content_sid set  → Twilio            → code T
        //   neither                 → Unofficial API    → code U
        $engine = $t->meta_template_id
            ? WorkspaceEngine::ENGINE_WABA
            : ($t->twilio_content_sid ? WorkspaceEngine::ENGINE_TWILIO : WorkspaceEngine::ENGINE_BAILEYS);
        $chan = WorkspaceEngine::descriptor($engine);

        // Creator: admin-seeded globals (no owner) read as "Admin"; otherwise
        // the owning user's name.
        $isGlobal    = $t->workspace_id === null && $t->user_id === null;
        $creatorType = $t->user_id ? 'user' : 'admin';
        $creatorName = $t->user_id ? ($this->userName((int) $t->user_id) ?: 'User #' . $t->user_id) : 'Admin';

        return [
            'id'                   => $t->id,
            'template_name'        => $t->template_name,
            'template_type'        => $t->template_type,
            // Both forms so the app can read whichever it knows.
            'category'             => $t->category,
            'meta_category'        => $t->meta_category,
            'category_id'          => self::META_TO_CATEGORY_ID[$t->meta_category] ?? null,
            'header'               => $t->header,
            'header_location'      => $t->header_location ?: null,
            'template_body'        => $t->template_body,
            'footer'               => $t->footer,
            'buttons'              => $t->buttons ?: [],
            'carousel_data'        => $t->carousel_data ?: [],
            'variable_map'         => $t->variable_map ?: [],
            'attachment_type'      => $t->attachment_type,
            'attachment_file'      => $this->attachmentUrl($t->attachment_file),
            'attachment_file_path' => $t->attachment_file,
            'language'             => $t->language,
            'status'               => $t->status,
            'meta_status'          => $t->meta_status,
            // Channel sign (Meta / Unofficial API / Twilio) for the app badge.
            'channel'              => $chan['channel'],   // meta | unofficial | twilio
            'channel_label'        => $chan['label'],     // Meta | Unofficial API | Twilio
            'channel_code'         => $chan['code'],      // W | U | T
            // Who created it: admin-seeded global vs a named user.
            'is_global'            => $isGlobal,
            'created_by'           => $t->user_id,
            'created_by_type'      => $creatorType,       // admin | user
            'created_by_name'      => $creatorName,       // "Admin" or the user's name
            'created_at'           => optional($t->created_at)->toIso8601String(),
            'updated_at'           => optional($t->updated_at)->toIso8601String(),
        ];
    }

    /** Per-request cache of [user_id => name] so the list never N+1s. */
    private array $userNameCache = [];

    /** Resolve a user's display name (cached), or null if not found. */
    private function userName(int $userId): ?string
    {
        if (array_key_exists($userId, $this->userNameCache)) {
            return $this->userNameCache[$userId];
        }
        return $this->userNameCache[$userId] = optional(User::find($userId))->name;
    }

    /** Full URL for a public-disk-relative attachment path, or null. */
    private function attachmentUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return media_url($path);
    }

    /**
     * Resolve the incoming `category` field (which may be the old numeric
     * Meta id 1/2/3, a meta slug, or one of our industry buckets) into our
     * industry `category` column value.
     */
    private function resolveCategory($category): string
    {
        $valid = WaTemplate::CATEGORIES; // travel, healthcare, ... utility
        if (is_string($category) && in_array($category, $valid, true)) {
            return $category;
        }
        // Numeric / meta-slug categories don't map to an industry bucket —
        // default to 'utility' (the column default) so the row stays valid.
        return 'utility';
    }

    /**
     * Resolve the incoming `category` into our `meta_category` slug
     * (marketing|authentication|utility). Accepts the old numeric id.
     */
    private function resolveMetaCategory($category): ?string
    {
        if (is_numeric($category)) {
            return self::CATEGORY_ID_TO_META[(int) $category] ?? null;
        }
        if (is_string($category)) {
            $slug = strtolower(trim($category));
            if (in_array($slug, ['marketing', 'authentication', 'utility'], true)) {
                return $slug;
            }
        }
        return null;
    }

    /** Map an uploaded file's MIME to our attachment_type enum. */
    private function resolveAttachmentType($file): string
    {
        $mime = (string) $file->getMimeType();
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        return 'document';
    }

    /**
     * Collect CTA buttons + quick replies into our canonical buttons[]
     * array. Accepts either a ready `buttons` array (app sends the final
     * shape) OR the old parallel-array form (button_type[]/button_text[]/…
     * plus quick_reply[]).
     */
    private function collectButtons(Request $request): array
    {
        $buttons = [];

        // CTA buttons — either the ready `buttons` array (app sends the final
        // shape) OR the old parallel-array form (button_type[]/button_text[]/…).
        // NOTE: this no longer returns early — quick_replies are ALWAYS folded
        // in afterwards, so a "mix" of CTA buttons + quick replies (which the
        // app sends as two separate arrays) is preserved instead of dropping
        // the quick replies.
        $direct = $request->input('buttons');
        if (is_array($direct) && ! empty($direct)) {
            foreach ($direct as $b) {
                if (! is_array($b)) continue;
                $buttons[] = array_filter([
                    'type'         => isset($b['type']) ? (string) $b['type'] : null,
                    'text'         => isset($b['text']) ? (string) $b['text'] : null,
                    'value'        => isset($b['value']) ? (string) $b['value'] : null,
                    'url_type'     => $b['url_type']     ?? null,
                    'country_code' => $b['country_code'] ?? null,
                ], fn ($v) => $v !== null);
            }
        } else {
            // Fallback: old parallel-array form.
            $types  = (array) $request->input('button_type', []);
            $texts  = (array) $request->input('button_text', []);
            $values = (array) $request->input('button_value', []);
            $ccs    = (array) $request->input('country_code', []);
            $urlT   = (array) $request->input('url_type', []);

            foreach ($types as $i => $type) {
                if (empty($texts[$i])) continue;
                $b = [
                    'type'  => (string) $type,
                    'text'  => (string) $texts[$i],
                    'value' => (string) ($values[$i] ?? ''),
                ];
                if ($type === 'call_phone' && isset($ccs[$i]))     $b['country_code'] = (string) $ccs[$i];
                if ($type === 'visit_website' && isset($urlT[$i])) $b['url_type']     = (string) $urlT[$i];
                $buttons[] = $b;
            }
        }

        // Quick replies → quick_reply buttons. Always appended so CTA + quick
        // reply mix freely (accepts ["text", ...] or [{type,text}, ...]).
        foreach ((array) $request->input('quick_replies', $request->input('quick_reply', [])) as $reply) {
            if (is_array($reply)) {
                $reply = $reply['text'] ?? '';
            }
            $reply = trim((string) $reply);
            if ($reply !== '') {
                $buttons[] = ['type' => 'quick_reply', 'text' => $reply];
            }
        }

        // WhatsApp reliably renders only 3 buttons — hard-cap to match the web
        // editor, TemplatesController::processButtons() and the Node formatter.
        return array_slice($buttons, 0, 3);
    }

    /**
     * Collect a LOCATION header from the request. Accepts either a nested
     * `header_location` object OR flat latitude/longitude/name/address fields.
     * Returns null when no usable coordinates are present (so callers can tell
     * "no location" from "cleared location").
     */
    private function collectLocation(Request $request): ?array
    {
        $loc = $request->input('header_location');
        if (! is_array($loc)) {
            $loc = [
                'latitude'  => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'name'      => $request->input('location_name'),
                'address'   => $request->input('location_address'),
            ];
        }

        $lat = trim((string) ($loc['latitude']  ?? $loc['lat'] ?? ''));
        $lng = trim((string) ($loc['longitude'] ?? $loc['lng'] ?? $loc['lon'] ?? ''));
        if ($lat === '' || $lng === '' || ! is_numeric($lat) || ! is_numeric($lng)
            || (float) $lat < -90 || (float) $lat > 90
            || (float) $lng < -180 || (float) $lng > 180) {
            return null; // not valid coordinates (out of range or empty) → no location
        }

        return array_filter([
            'latitude'  => (string) $lat,
            'longitude' => (string) $lng,
            'name'      => isset($loc['name'])    ? (string) $loc['name']    : null,
            'address'   => isset($loc['address']) ? (string) $loc['address'] : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Build the positional WABA variable map from `{var}` / `{{var}}`
     * tokens in the header + body. Mirrors the old controller's
     * buildVariableMap. Returns null when no vars are found.
     *
     * Output: ['header' => [['num'=>1,'key'=>'name']], 'body' => [...]]
     */
    private function buildVariableMap(?string $header, ?string $body): ?array
    {
        $extract = function (?string $text): array {
            if (! $text) return [];
            $out  = [];
            $i    = 1;
            $seen = [];
            if (preg_match_all('/\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/', $text, $m)) {
                foreach ($m[1] as $varName) {
                    if (isset($seen[$varName])) continue;
                    $seen[$varName] = true;
                    $out[] = ['num' => $i++, 'key' => $varName];
                }
            }
            return $out;
        };

        $map = [];
        if ($h = $extract($header)) $map['header'] = $h;
        if ($b = $extract($body))   $map['body']   = $b;

        return $map ?: null;
    }

    /**
     * Decode any of the named fields that arrived as a JSON string into a
     * real array on the request, so downstream code can treat them
     * uniformly. No-op for fields that are already arrays or absent.
     */
    private function normalizeJsonArrays(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            $val = $request->input($field);
            if (is_string($val) && $val !== '') {
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge([$field => $decoded]);
                }
            }
        }
    }

    /** Uniform error shape for the mobile API. */
    private function fail(\Throwable $e, string $action): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Error ' . $action,
            'error'   => $e->getMessage(),
        ], 500);
    }
}

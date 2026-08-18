<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\App\TemplateController as AppTemplateController;
use App\Http\Requests\Api\V1\Template\StoreTemplateRequest;
use App\Http\Requests\Api\V1\Template\UpdateTemplateRequest;
use App\Http\Resources\Api\V1\TemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Templates — manage the workspace's WhatsApp message templates.
 *
 * Reuses the tested mobile-app pipeline (App\Http\Controllers\Api\App\
 * TemplateController → WaTemplate, workspace-scoped) and re-wraps every
 * result in the public { data } / { error } envelope.
 */
class TemplateController extends V1Controller
{
    /** GET /api/v1/templates — list the workspace's templates. */
    public function index(Request $request): JsonResponse
    {
        $internal = Request::create('/api/app/get-templates', 'GET');
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppTemplateController::class)->index($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('list_failed', $payload['message'] ?? 'Templates could not be listed.', 422);
        }

        $items = collect($payload['templates'] ?? [])
            ->map(fn ($t) => (new TemplateResource($t))->resolve())
            ->values();

        return $this->ok($items, ['count' => $items->count()]);
    }

    /** GET /api/v1/templates/categories — the Meta category list. */
    public function categories(): JsonResponse
    {
        $payload = app(AppTemplateController::class)->categories()->getData(true);

        return $this->ok($payload['categories'] ?? []);
    }

    /** POST /api/v1/templates — create a template. */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $internal = Request::create('/api/app/templates-store', 'POST', $this->forwardFields($request));
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppTemplateController::class)->store($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail(
                'create_failed',
                $payload['message'] ?? 'Template could not be created.',
                422,
                (array) ($payload['errors'] ?? [])
            );
        }

        return $this->created((new TemplateResource($payload['data'] ?? []))->resolve());
    }

    /** GET /api/v1/templates/{id} — single template detail. */
    public function show(int $id): JsonResponse
    {
        $internal = Request::create('/api/app/templates/' . $id, 'GET');
        $internal->setUserResolver(fn () => request()->user());

        $payload = app(AppTemplateController::class)->show($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('not_found', $payload['message'] ?? 'Template not found.', 404);
        }

        return $this->ok((new TemplateResource($payload['data'] ?? []))->resolve());
    }

    /** PUT /api/v1/templates/{id} — update a template. */
    public function update(UpdateTemplateRequest $request, int $id): JsonResponse
    {
        $internal = Request::create('/api/app/templates/' . $id, 'PUT', $this->forwardFields($request));
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppTemplateController::class)->update($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            $status = isset($payload['errors']) ? 422 : 404;
            return $this->fail(
                $status === 422 ? 'update_failed' : 'not_found',
                $payload['message'] ?? 'Template could not be updated.',
                $status,
                (array) ($payload['errors'] ?? [])
            );
        }

        return $this->ok((new TemplateResource($payload['data'] ?? []))->resolve());
    }

    /**
     * The field set forwarded to the mobile-app controller for store + update.
     * Includes the LOCATION header (flat latitude/longitude/location_name/
     * location_address) so location templates are reachable via the public API
     * — the App controller's collectLocation() reads exactly these keys.
     *
     * Note: file attachments (image/video/document) can't be uploaded through
     * this internal sub-request and are intentionally out of scope here; create
     * media templates in the dashboard.
     */
    private function forwardFields(Request $request): array
    {
        // Forward ONLY the keys the caller actually sent. Using input() for
        // every field would turn an omitted field into an explicit null, which
        // on a partial update overwrites the stored value (e.g. nulling a
        // NOT-NULL `language` column). only() omits absent keys so the App
        // controller's "keep existing value" defaults apply.
        return $request->only([
            'template_name', 'template_type', 'category', 'header',
            'template_body', 'footer', 'language', 'buttons', 'quick_replies',
            'carousel_data',
            // Authentication (OTP) code validity in minutes (1–90; default 10).
            'code_expiration_minutes',
            // LOCATION header pin — folded into header_location by the App layer.
            'latitude', 'longitude', 'location_name', 'location_address',
        ]);
    }

    /** DELETE /api/v1/templates/{id} — delete a template. */
    public function destroy(int $id): JsonResponse
    {
        $internal = Request::create('/api/app/templates/' . $id, 'DELETE');
        $internal->setUserResolver(fn () => request()->user());

        $payload = app(AppTemplateController::class)->destroy($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('not_found', $payload['message'] ?? 'Template not found.', 404);
        }

        return $this->ok(['deleted' => true, 'message' => $payload['message'] ?? 'Template deleted successfully']);
    }

    /**
     * POST /api/v1/templates/{id}/send — send one approved WhatsApp template to a
     * single recipient with your own variable values (WABA Cloud). This is the
     * single message + variables send that the list/broadcast endpoints don't cover.
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            // Recipient phone in international format — digits or +E.164 (e.g. 919812345678). Required.
            'to'                 => 'required|string|max:32',
            // BODY variables, positional -> {{1}}, {{2}}, ... Send a list ["John","12345"]
            // or a 1-indexed map {"1":"John","2":"12345"}. Omit if the body has no variables.
            'body'               => 'nullable|array',
            'body.*'             => 'nullable|string|max:1024',
            // Value for a {{1}} in a TEXT header. Only for text-header templates.
            'header_text'        => 'nullable|string|max:1024',
            // Public https URL for an IMAGE / VIDEO / DOCUMENT header. Only for media-header templates.
            'header_media_url'   => 'nullable|url|max:2048',
            // OR attach the header file (PDF / image / video) directly in ONE multipart/form-data
            // request — field name `header_media`, up to 16 MB. We host it and use it as the header,
            // so you don't need a separate upload call. Takes precedence over header_media_url.
            'header_media'       => 'nullable|file|max:16384',
            // Display filename the recipient sees for a DOCUMENT header (e.g. "Invoice-2045.pdf").
            // Optional — when you attach header_media we default it to the uploaded file's own name.
            'header_filename'    => 'nullable|string|max:255',
            // Dynamic button parameters, one object per variable button, e.g. [{"index":0,"sub_type":"url","value":"ORDER123"}].
            'buttons'            => 'nullable|array',
            // Zero-based position of the button in the template.
            'buttons.*.index'    => 'nullable|integer|min:0',
            // Button kind: "url" (dynamic URL suffix) or "quick_reply" (payload).
            'buttons.*.sub_type' => 'nullable|string|max:32',
            // The dynamic value — URL suffix or quick-reply payload.
            'buttons.*.value'    => 'nullable|string|max:2048',
        ]);

        // One-call convenience: if the header file was attached inline (multipart
        // `header_media`), host it with the SAME secure-upload guard as POST /media
        // and use it as the media header. This lets a caller send the PDF/image +
        // variables + button in a SINGLE multipart/form-data request instead of
        // uploading first. An inline file wins over a supplied header_media_url.
        if ($request->hasFile('header_media')) {
            $mediaFile = $request->file('header_media');
            if ($problem = \App\Support\SecureUpload::problem($mediaFile)) {
                return $this->fail('unsupported_media_type', $problem, 422);
            }
            $mediaPath = $mediaFile->storeAs('chat-media', \App\Support\SecureUpload::safeName($mediaFile), media_disk());
            $data['header_media_url'] = media_url($mediaPath);
            // Keep the uploaded file's OWN name as the WhatsApp document filename —
            // without it a document header shows as "Untitled". An explicit
            // header_filename the caller sent still wins.
            if (empty($data['header_filename'])) {
                $data['header_filename'] = $mediaFile->getClientOriginalName();
            }
        }

        $tpl = \App\Models\WaTemplate::query()
            ->forCurrentWorkspace()
            ->whereKey($id)
            ->first();
        if (!$tpl) {
            return $this->fail('template_not_found', 'Template not found in this workspace.', 404);
        }

        // Body vars → positional list. Accept a list OR a 1-indexed map so both
        // {"body":["John","12345"]} and {"body":{"1":"John","2":"12345"}} work.
        $bodyIn = $data['body'] ?? [];
        if (array_is_list($bodyIn)) {
            $body = array_map('strval', $bodyIn);
        } else {
            ksort($bodyIn, SORT_NUMERIC);
            $body = array_values(array_map('strval', $bodyIn));
        }

        $vars = [];
        if (!empty($body))                     $vars['body']             = $body;
        if (!empty($data['header_text']))      $vars['header']           = (string) $data['header_text'];
        if (!empty($data['header_media_url'])) $vars['header_media_url'] = (string) $data['header_media_url'];
        if (!empty($data['header_filename']))  $vars['header_filename']  = (string) $data['header_filename'];
        if (!empty($data['buttons'])) {
            $vars['buttons'] = array_values(array_map(fn ($b) => [
                'index'    => (int) ($b['index'] ?? 0),
                'sub_type' => (string) ($b['sub_type'] ?? 'url'),
                'value'    => (string) ($b['value'] ?? ''),
            ], $data['buttons']));
        }

        $result = app(\App\Services\Waba\TemplateSender::class)->send($tpl, (string) $data['to'], $vars);

        // Auto-capture the recipient as a contact (dedup by phone hash).
        \App\Models\Contact::rememberPhone($this->workspaceId(), $request->user()?->id, (string) $data['to']);

        if (($result['ok'] ?? false) !== true) {
            return $this->fail(
                $result['code'] ?? 'send_failed',
                $result['error'] ?? 'Template send failed.',
                422,
                ['template_id' => $tpl->id]
            );
        }

        // Mirror into the Team Inbox. TemplateSender itself writes nothing —
        // no Message, no InboxMessage, no Conversation — so an API template
        // send was invisible to agents: the customer would reply and the
        // operator saw an answer with no question above it.
        //
        // allow_create is passed because this is a genuine 1:1 send, unlike the
        // campaign/broadcast callers of this mirror which must never open a
        // thread. Best-effort: a mirroring failure must not fail a send that
        // already left the building.
        try {
            // Render what the CUSTOMER saw, not raw {{1}} placeholders: fill the
            // positional body vars into the stored template text. wa_templates
            // stores header/template_body/footer, which is exactly the shape
            // InboxMirror::readableTemplateBody() consumes.
            $filled = (string) $tpl->template_body;
            foreach ($body as $i => $val) {
                $filled = str_replace('{{' . ($i + 1) . '}}', (string) $val, $filled);
            }
            $bubble = \App\Services\Inbox\InboxMirror::readableTemplateBody([
                'header'        => $vars['header'] ?? (string) $tpl->header,
                'template_body' => $filled,
                'footer'        => (string) $tpl->footer,
            ]);

            app(\App\Services\Inbox\InboxMirror::class)->appendOutboundToOpenConversation(
                (int) $this->workspaceId(),
                (string) $data['to'],
                $bubble !== '' ? $bubble : ('[template] ' . $tpl->template_name),
                $result['wamid'] ?? null,
                'waba',
                array_filter([
                    'source'        => 'api',
                    'type'          => 'template',
                    'template_name' => $tpl->template_name,
                    // Buttons so the inbox bubble renders the template's CTA rows,
                    // matching a team-inbox send instead of showing plain text.
                    'buttons'       => (is_array($tpl->buttons) && $tpl->buttons) ? $tpl->buttons : null,
                    'allow_create'  => true,
                ], fn ($v) => $v !== null)
            );
        } catch (\Throwable $e) {
            \Log::warning('[API-V1] template sent but inbox mirror failed', [
                'template_id' => $tpl->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return $this->created([
            'wamid'         => $result['wamid'] ?? null,
            'status'        => 'sent',
            'template_id'   => $tpl->id,
            'template_name' => $tpl->template_name,
            'to'            => preg_replace('/\D+/', '', (string) $data['to']),
        ]);
    }
}

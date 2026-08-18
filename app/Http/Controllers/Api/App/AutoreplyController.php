<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Models\KeywordReply;
use App\Models\KeywordReplyContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile-app Autoreplies + Flows API (WaDesk).
 *
 * The old app reads an "autoreply" as a `KeywordReply` row carrying a
 * `messages` array (one entry per reply variant) and an optional `flow`.
 * OUR KeywordReply stores reply variants on a `contents` relation
 * (model: KeywordReplyContent) whose `content_type` column is the old
 * `message_type`, so we remap our contents onto a `messages` key here so
 * the response stays byte-compatible with the app.
 *
 * Scope: the old controller scoped by `user_id`; ours is workspace-shared,
 * so every query is scoped to the Sanctum user's current_workspace_id.
 *
 * Full CRUD: index/show/getflows (read) + store/update/destroy (write).
 * The write path accepts the OLD app's multipart payload field names
 * (keyword, matching_method, fuzzy_similarity, device_id, reply_type,
 * flow_id, status, plus text_messages[]/images[]/videos[]/documents[]/
 * template_ids[] with matching checked_*[] index arrays) and returns the
 * OLD response shape ({success, data, message}) — but it persists against
 * OUR models: each variant becomes a KeywordReplyContent row whose
 * `content_type` is the old `message_type`, workspace-scoped (not the old
 * KeywordReplyMessage + user_id model).
 */
class AutoreplyController extends Controller
{
    /**
     * GET /autoreplies — list the workspace's autoreplies.
     * Each row carries `messages` (selected only, to match the old index
     * eager-load) and `flow`.
     * Shape: { success, data: [ ...keywordReply ], message }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $wsId = (int) ($request->user()->current_workspace_id ?? 0);

            $autoreplies = KeywordReply::where('workspace_id', $wsId)
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $autoreplies->map(fn ($r) => $this->transformAutoreply($r, selectedOnly: true))->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Autoreplies retrieved successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App\AutoreplyController@index failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve autoreplies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /autoreplies/{id} — one autoreply with ALL messages + flow.
     * Shape: { success, data: keywordReply, message }
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $wsId = (int) ($request->user()->current_workspace_id ?? 0);

            $autoreply = KeywordReply::where('workspace_id', $wsId)
                ->where('id', $id)
                ->first();

            if (!$autoreply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Autoreply not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->transformAutoreply($autoreply, selectedOnly: false),
                'message' => 'Autoreply retrieved successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App\AutoreplyController@show failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve autoreply',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /getflows — list the workspace's flows.
     * Shape: { success, flow: [ ...flow ], message }
     */
    public function getflows(Request $request): JsonResponse
    {
        try {
            $flows = Flow::query()->forCurrentWorkspace()
                ->orderByDesc('id')
                ->get()
                ->map(fn ($f) => $this->transformFlow($f))
                ->values();

            return response()->json([
                'success' => true,
                'flow' => $flows,
                'message' => 'Success',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App\AutoreplyController@getflows failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'flow' => [],
                'message' => 'Failed to retrieve flows',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /autoreplies — create an autoreply (custom messages or flow).
     * Payload field names mirror the old app; persisted against OUR
     * KeywordReply + KeywordReplyContent, workspace-scoped.
     * Shape: { success, data, message }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = $this->validatePayload($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $wsId = (int) ($user->current_workspace_id ?? 0);

        DB::beginTransaction();
        try {
            $reply = KeywordReply::create([
                'workspace_id'     => $wsId,
                'user_id'          => $user->id,
                'keyword'          => $request->keyword,
                'matching_method'  => $request->matching_method,
                'fuzzy_similarity' => $request->fuzzy_similarity ?? 80,
                'device_id'        => $request->device_id,
                'status'           => $request->boolean('status'),
                'reply_type'       => $request->reply_type,
                'flow_id'          => $request->reply_type === 'flow' ? $request->flow_id : null,
            ]);

            if ($request->reply_type === 'custom') {
                $type = $this->storeAllContents($request, $reply);
                if ($type) {
                    $reply->update(['message_type' => $type]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $this->transformAutoreply($reply->fresh(), selectedOnly: false),
                'message' => 'Autoreply created successfully',
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('App\AutoreplyController@store failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create autoreply',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT|POST /autoreplies/{id} — update an autoreply. Same payload as
     * store; appends any newly-uploaded contents. Workspace-scoped.
     * Shape: { success, data, message }
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = $this->validatePayload($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $wsId = (int) ($request->user()->current_workspace_id ?? 0);

        DB::beginTransaction();
        try {
            $reply = KeywordReply::where('workspace_id', $wsId)->where('id', $id)->first();

            if (!$reply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Autoreply not found',
                ], 404);
            }

            $reply->update([
                'keyword'          => $request->keyword,
                'matching_method'  => $request->matching_method,
                'fuzzy_similarity' => $request->fuzzy_similarity ?? 80,
                'device_id'        => $request->device_id,
                'status'           => $request->boolean('status'),
                'reply_type'       => $request->reply_type,
                'flow_id'          => $request->reply_type === 'flow' ? $request->flow_id : null,
            ]);

            if ($request->reply_type === 'custom') {
                $type = $this->storeAllContents($request, $reply);
                if ($type) {
                    $reply->update(['message_type' => $type]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $this->transformAutoreply($reply->fresh(), selectedOnly: false),
                'message' => 'Autoreply updated successfully',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('App\AutoreplyController@update failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update autoreply',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /autoreplies/{id} — delete an autoreply + its content files.
     * Workspace-scoped. Shape: { success, message }
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);

        DB::beginTransaction();
        try {
            $reply = KeywordReply::where('workspace_id', $wsId)->where('id', $id)->first();

            if (!$reply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Autoreply not found',
                ], 404);
            }

            foreach ($reply->contents()->get() as $content) {
                if ($content->file_path) {
                    $full = public_path($content->file_path);
                    if (File::exists($full)) {
                        File::delete($full);
                    }
                }
            }

            $reply->contents()->delete();
            $reply->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Autoreply deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('App\AutoreplyController@destroy failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete autoreply',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /**
     * Shared store/update validation — the old app's payload field names.
     */
    private function validatePayload(Request $request)
    {
        return Validator::make($request->all(), [
            'keyword'           => 'required|string|max:255',
            'matching_method'   => 'required|in:fuzzy,exact,contains',
            'fuzzy_similarity'  => 'nullable|integer|min:0|max:100',
            'device_id'         => 'required|string',
            'reply_type'        => 'required|in:custom,flow',
            'flow_id'           => 'required_if:reply_type,flow|nullable|exists:flows,id',
            'status'            => 'nullable|boolean',
            'text_messages'     => 'nullable|array',
            'text_messages.*'   => 'string',
            'checked_texts'     => 'nullable|array',
            'images'            => 'nullable|array',
            'images.*'          => 'file|mimes:jpeg,jpg,png,gif|max:10240',
            'checked_images'    => 'nullable|array',
            'videos'            => 'nullable|array',
            'videos.*'          => 'file|mimes:mp4,avi,mov,wmv|max:51200',
            'checked_videos'    => 'nullable|array',
            'documents'         => 'nullable|array',
            'documents.*'       => 'file|max:10240',
            'checked_documents' => 'nullable|array',
            'template_ids'      => 'nullable|array',
            'checked_templates' => 'nullable|array',
        ]);
    }

    /**
     * Persist all custom-message contents from the request into OUR
     * KeywordReplyContent rows (content_type = old message_type). Mirrors
     * the old storeAllMessages field handling; returns the selected type.
     */
    private function storeAllContents(Request $request, KeywordReply $reply): ?string
    {
        $sortOrder    = (int) ($reply->contents()->max('sort_order') ?? 0);
        $selectedType = null;

        $checkedTexts     = $request->input('checked_texts', []);
        $checkedTemplates = $request->input('checked_templates', []);

        if (!empty($checkedTexts)) {
            $selectedType = 'text';
        } elseif (!empty($request->input('checked_images', []))) {
            $selectedType = 'image';
        } elseif (!empty($request->input('checked_videos', []))) {
            $selectedType = 'video';
        } elseif (!empty($request->input('checked_documents', []))) {
            $selectedType = 'document';
        } elseif (!empty($checkedTemplates)) {
            $selectedType = 'template';
        }

        // Text variants
        foreach ((array) $request->input('text_messages', []) as $index => $text) {
            if (trim((string) $text) !== '') {
                KeywordReplyContent::create([
                    'keyword_reply_id' => $reply->id,
                    'content_type'     => 'text',
                    'content'          => $text,
                    'is_selected'      => in_array($index, $checkedTexts),
                    'sort_order'       => ++$sortOrder,
                ]);
            }
        }

        // Image / video / document uploads
        foreach (['image' => 'images', 'video' => 'videos', 'document' => 'documents'] as $type => $field) {
            if ($request->hasFile($field)) {
                $checked = $request->input('checked_' . $field, []);
                foreach ($request->file($field) as $index => $file) {
                    // Read size + mime BEFORE storeFileInPublic() — it move()s the
                    // upload, after which getSize()/getMimeType() stat the gone
                    // /tmp file and throw "stat failed for /tmp/php…".
                    $origName = $file->getClientOriginalName();
                    $size     = $file->getSize();
                    $mime     = $file->getMimeType();
                    KeywordReplyContent::create([
                        'keyword_reply_id' => $reply->id,
                        'content_type'     => $type,
                        'file_path'        => $this->storeFileInPublic($file, $field),
                        'original_name'    => $origName,
                        'file_size'        => $size,
                        'mime_type'        => $mime,
                        'is_selected'      => in_array($index, $checked),
                        'sort_order'       => ++$sortOrder,
                    ]);
                }
            }
        }

        // Template variants
        foreach ((array) $request->input('template_ids', []) as $index => $templateId) {
            if (!empty($templateId)) {
                KeywordReplyContent::create([
                    'keyword_reply_id' => $reply->id,
                    'content_type'     => 'template',
                    'template_id'      => $templateId,
                    'is_selected'      => in_array($index, $checkedTemplates),
                    'sort_order'       => ++$sortOrder,
                ]);
            }
        }

        return $selectedType;
    }

    /** Move an uploaded file into public/uploads/autoreply/<type> (relative path stored). */
    private function storeFileInPublic($file, string $type): string
    {
        $uploadPath = public_path('uploads/autoreply/' . $type);
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($uploadPath, $filename);

        return 'uploads/autoreply/' . $type . '/' . $filename;
    }

    /**
     * Serialize a KeywordReply into the old app's autoreply shape. Our
     * `contents` relation is remapped onto the `messages` key, and
     * `content_type` → `message_type`, to mirror the old KeywordReplyMessage.
     */
    private function transformAutoreply(KeywordReply $reply, bool $selectedOnly): array
    {
        $contents = $selectedOnly
            ? $reply->selectedContents()->get()
            : $reply->contents()->get();

        $messages = $contents->map(fn ($c) => $this->transformContent($c))->values();

        $flow = $reply->flow_id ? Flow::find($reply->flow_id) : null;

        return [
            'id' => $reply->id,
            'keyword' => $reply->keyword,
            'matching_method' => $reply->matching_method,
            'fuzzy_similarity' => $reply->fuzzy_similarity,
            'user_id' => $reply->user_id,
            'device_id' => $reply->device_id,
            'message_type' => $reply->message_type,
            'status' => (int) $reply->status,
            'reply_type' => $reply->reply_type,
            'flow_id' => $reply->flow_id,
            'cooldown' => $reply->cooldown,
            'timeout' => $reply->timeout,
            'created_at' => $reply->created_at,
            'updated_at' => $reply->updated_at,
            'messages' => $messages,
            'flow' => $flow ? $this->transformFlow($flow) : null,
        ];
    }

    /**
     * Map one KeywordReplyContent to the old KeywordReplyMessage element.
     * `content_type` is the old `message_type`; `url` mirrors the old
     * appended accessor (asset() of file_path).
     */
    private function transformContent(KeywordReplyContent $c): array
    {
        return [
            'id' => $c->id,
            'keyword_reply_id' => $c->keyword_reply_id,
            'message_type' => $c->content_type,
            'content' => $c->content,
            'file_path' => $c->file_path,
            'original_name' => $c->original_name,
            'file_size' => $c->file_size,
            'mime_type' => $c->mime_type,
            'template_id' => $c->template_id,
            'is_selected' => (bool) $c->is_selected,
            'sort_order' => (int) $c->sort_order,
            'url' => $c->file_path ? asset($c->file_path) : null,
            'created_at' => $c->created_at,
            'updated_at' => $c->updated_at,
        ];
    }

    private function transformFlow(Flow $flow): array
    {
        return [
            'id' => $flow->id,
            'user_id' => $flow->user_id,
            'flow_name' => $flow->flow_name,
            'category' => $flow->category,
            'is_published' => (bool) $flow->is_published,
            'is_active' => (bool) $flow->is_active,
            'published_at' => $flow->published_at,
            'created_at' => $flow->created_at,
            'updated_at' => $flow->updated_at,
        ];
    }
}

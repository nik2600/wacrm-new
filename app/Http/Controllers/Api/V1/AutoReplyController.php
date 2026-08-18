<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\App\AutoreplyController as AppAutoreplyController;
use App\Http\Requests\Api\V1\AutoReply\StoreAutoReplyRequest;
use App\Http\Requests\Api\V1\AutoReply\UpdateAutoReplyRequest;
use App\Http\Resources\Api\V1\AutoReplyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Auto-replies — keyword-triggered automatic responses.
 *
 * Reuses the tested auto-reply pipeline (AutoreplyController on the App API →
 * KeywordReply + KeywordReplyContent), wrapping the result in the public
 * { data } envelope. The public `replies[]` text variants are mapped onto the
 * underlying controller's `text_messages[]` + `checked_texts[]` keys.
 */
class AutoReplyController extends V1Controller
{
    /** GET /api/v1/auto-replies — list the workspace's auto-replies. */
    public function index(Request $request): JsonResponse
    {
        $internal = Request::create('/api/app/autoreplies', 'GET');
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppAutoreplyController::class)->index($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('list_failed', $payload['message'] ?? 'Could not list auto-replies.', 422);
        }

        $items = collect($payload['data'] ?? [])
            ->map(fn ($r) => (new AutoReplyResource($r))->resolve())
            ->values();

        return $this->ok($items, ['count' => $items->count()]);
    }

    /** POST /api/v1/auto-replies — create an auto-reply. */
    public function store(StoreAutoReplyRequest $request): JsonResponse
    {
        $internal = $this->buildWriteRequest('/api/app/autoreplies', 'POST', $request);

        $payload = app(AppAutoreplyController::class)->store($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail(
                'create_failed',
                $payload['message'] ?? 'Could not create auto-reply.',
                422,
                $payload['errors'] ?? []
            );
        }

        return $this->created((new AutoReplyResource($payload['data']))->resolve());
    }

    /** GET /api/v1/auto-replies/{id} — one auto-reply. */
    public function show(Request $request, int $id): JsonResponse
    {
        $internal = Request::create('/api/app/autoreplies/' . $id, 'GET');
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppAutoreplyController::class)->show($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('not_found', $payload['message'] ?? 'Auto-reply not found.', 404);
        }

        return $this->ok((new AutoReplyResource($payload['data']))->resolve());
    }

    /** PUT /api/v1/auto-replies/{id} — update an auto-reply. */
    public function update(UpdateAutoReplyRequest $request, int $id): JsonResponse
    {
        $internal = $this->buildWriteRequest('/api/app/autoreplies/' . $id, 'PUT', $request);

        $payload = app(AppAutoreplyController::class)->update($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            $status = str_contains(strtolower($payload['message'] ?? ''), 'not found') ? 404 : 422;
            return $this->fail($status === 404 ? 'not_found' : 'update_failed', $payload['message'] ?? 'Could not update auto-reply.', $status, $payload['errors'] ?? []);
        }

        return $this->ok((new AutoReplyResource($payload['data']))->resolve());
    }

    /** DELETE /api/v1/auto-replies/{id} — delete an auto-reply. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $internal = Request::create('/api/app/autoreplies/' . $id, 'DELETE');
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppAutoreplyController::class)->destroy($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('not_found', $payload['message'] ?? 'Auto-reply not found.', 404);
        }

        return $this->ok(['id' => $id, 'deleted' => true]);
    }

    /**
     * Build the internal write Request, translating the public payload onto the
     * underlying controller's field names. The public `replies[]` array becomes
     * `text_messages[]`, with every index marked selected via `checked_texts[]`
     * (the underlying store/update only persists checked variants).
     */
    private function buildWriteRequest(string $uri, string $method, Request $request): Request
    {
        $replyType = $request->input('reply_type', 'custom');

        $params = [
            'keyword'          => $request->input('keyword'),
            'matching_method'  => $request->input('matching_method', 'exact'),
            'fuzzy_similarity' => $request->input('fuzzy_similarity'),
            'device_id'        => (string) $request->input('device_id'),
            'reply_type'       => $replyType,
            'flow_id'          => $request->input('flow_id'),
            'status'           => $request->boolean('status', true) ? 1 : 0,
        ];

        if ($replyType === 'custom') {
            $replies = array_values(array_filter(
                (array) $request->input('replies', []),
                fn ($v) => trim((string) $v) !== ''
            ));
            $params['text_messages'] = $replies;
            $params['checked_texts'] = array_keys($replies);
        }

        $internal = Request::create($uri, $method, $params);
        $internal->setUserResolver(fn () => $request->user());

        return $internal;
    }
}

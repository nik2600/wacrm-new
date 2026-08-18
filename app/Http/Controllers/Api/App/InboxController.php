<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile-app Inbox bundle.
 *
 * The chat-list / inbox screen used to fan out to SEVEN endpoints on every open
 * (/chats, /groups, /get-contact-groups, /get-queues, /queues/pinned,
 * /all-archive-queue, /chats/archived). This is ONE call that returns all of it.
 *
 * Each sub-payload is the EXACT JSON its own endpoint returns — we invoke the
 * very same controllers and collect their responses — so the app can keep
 * parsing each part with its existing models. Nothing about the individual
 * endpoints changes; they stay for anything that still wants a single slice.
 */
class InboxController extends Controller
{
    /** GET /inbox — everything the chat-list screen needs, in one response. */
    public function bundle(Request $request): JsonResponse
    {
        // Each is invoked with the SAME request, so filters (e.g. ?filter=all)
        // and the X-Workspace-Id / X-Device-Id scoping apply uniformly. A single
        // slice failing must not fail the whole bundle — wrap each so the app
        // still gets the parts that succeeded.
        $slice = function (callable $fn) {
            try {
                return $fn()->getData(true);
            } catch (\Throwable $e) {
                \Log::warning('[inbox-bundle] slice failed: ' . $e->getMessage());
                return ['success' => false, 'error' => 'slice_failed'];
            }
        };

        return response()->json([
            'success' => true,
            'data'    => [
                'chats'           => $slice(fn () => app(ChatController::class)->index($request)),
                'groups'          => $slice(fn () => app(GroupController::class)->index($request)),
                'contact_groups'  => $slice(fn () => app(ContactGroupController::class)->index($request)),
                'queues'          => $slice(fn () => app(QueueController::class)->getQueues($request)),
                'pinned_queues'   => $slice(fn () => app(QueueController::class)->getPinnedQueues($request)),
                'archived_queues' => $slice(fn () => app(QueueController::class)->all_archive_queue($request)),
                'archived_chats'  => $slice(fn () => app(ChatController::class)->archivedIndex($request)),
            ],
        ]);
    }
}

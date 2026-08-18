<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Conversations — read the workspace inbox: the chat threads and the
 * messages inside each one. Read-only (sending is POST /messages).
 */
class ConversationController extends V1Controller
{
    /** GET /api/v1/conversations — recent chat threads (newest first). */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        // `origin` records which surface opened a thread, not what the thread
        // IS — and now that there is one thread per number, whichever surface
        // happened to be first owns that label. Filtering on a single value
        // would return an arbitrary subset of the customer's threads, so use
        // the same scope the inbox uses (chat + inbox + chatbot, excluding
        // campaign rows, which live under /broadcasts).
        $p = Conversation::query()
            ->where('workspace_id', $this->workspaceId())
            ->chatOnly()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->ok(
            collect($p->items())->map(fn (Conversation $c) => $this->shape($c))->all(),
            ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()]
        );
    }

    /** GET /api/v1/conversations/{id}/messages — messages in one thread. */
    public function messages(Request $request, int $id): JsonResponse
    {
        $conv = Conversation::query()
            ->where('workspace_id', $this->workspaceId())
            ->whereKey($id)
            ->first();

        if (!$conv) {
            return $this->fail('not_found', 'Conversation not found.', 404);
        }

        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);
        $p = Message::query()
            ->where('conversation_id', $conv->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->ok(
            collect($p->items())->map(fn (Message $m) => (new MessageResource($m))->resolve())->all(),
            ['conversation_id' => $conv->id, 'page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()]
        );
    }

    /** Public shape of a conversation thread. */
    private function shape(Conversation $c): array
    {
        // raw_jid looks like "919812345678@s.whatsapp.net" — expose just the number.
        $phone = preg_replace('/\D+/', '', (string) explode('@', (string) $c->raw_jid)[0]);

        return [
            'id'              => $c->id,
            'contact'         => $phone ?: null,
            'name'            => $c->title,
            'last_message'    => $c->preview,
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'unread'          => (int) ($c->unread_count ?? 0),
            'status'          => $c->status,
            'channel'         => $c->platform,
        ];
    }
}

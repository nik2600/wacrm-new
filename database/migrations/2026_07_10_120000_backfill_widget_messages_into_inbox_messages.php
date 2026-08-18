<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chatbot-widget chats used to persist their bubbles into the `messages`
 * table, but the team-inbox thread view reads `inbox_messages`. That left
 * every existing widget conversation opening BLANK in the team inbox
 * (a preview in the list, total_on_conv=0 in the thread).
 *
 * The controller now writes widget bubbles into `inbox_messages` going
 * forward. This one-time backfill copies the ALREADY-STORED widget
 * messages across so historical widget threads show up too.
 *
 * Idempotent: each copied row is tagged meta.widget_backfill=true and we
 * skip any conversation that already has a backfilled row, so a re-run
 * (or a partial previous run) never duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversations') || !Schema::hasTable('messages') || !Schema::hasTable('inbox_messages')) {
            return;
        }

        \App\Models\Conversation::query()
            ->where('raw_jid', 'like', 'widget-%')
            ->orderBy('id')
            ->chunkById(100, function ($conversations) {
                foreach ($conversations as $conv) {
                    // Already backfilled? Skip the whole conversation.
                    $alreadyDone = \App\Models\InboxMessage::query()
                        ->where('conversation_id', $conv->id)
                        ->where('meta->widget_backfill', true)
                        ->exists();
                    if ($alreadyDone) {
                        continue;
                    }

                    $provider = $conv->provider
                        ?: (\App\Services\WorkspaceEngine::for((int) $conv->workspace_id) ?: 'baileys');

                    // Read the source rows through the Message model so the
                    // encrypted columns decrypt; write through InboxMessage
                    // so they re-encrypt under the same APP_KEY. Ascending id
                    // so the new rows keep the original chronological order.
                    \App\Models\Message::query()
                        ->where('conversation_id', $conv->id)
                        ->orderBy('id')
                        ->chunkById(200, function ($messages) use ($conv, $provider) {
                            foreach ($messages as $m) {
                                $dir    = ($m->direction === 'in') ? 'in' : 'out';
                                $status = in_array($m->status, ['pending', 'sent', 'delivered', 'read', 'failed'], true)
                                    ? $m->status
                                    : ($dir === 'in' ? 'delivered' : 'sent');

                                $meta = is_array($m->meta) ? $m->meta : [];
                                $meta['widget_backfill'] = true;
                                if (!isset($meta['channel'])) {
                                    $meta['channel'] = 'chatbot_widget';
                                }

                                $im = new \App\Models\InboxMessage();
                                $im->forceFill([
                                    'conversation_id' => $conv->id,
                                    'user_id'         => $m->user_id,
                                    'agent_id'        => $m->agent_id,
                                    'contact_id'      => $m->contact_id,
                                    'provider'        => $provider,
                                    'direction'       => $dir,
                                    'to_number'       => $m->to_number,
                                    'from_number'     => $m->from_number,
                                    'body'            => $m->body,
                                    'media_path'      => $m->media_path,
                                    'media_type'      => $m->media_type,
                                    'latitude'        => $m->latitude,
                                    'longitude'       => $m->longitude,
                                    'status'          => $status,
                                    'reaction'        => $m->reaction,
                                    'meta'            => $meta,
                                    'sent_at'         => $m->sent_at,
                                    'delivered_at'    => $m->delivered_at,
                                    'read_at'         => $m->read_at,
                                    'created_at'      => $m->created_at,
                                    'updated_at'      => $m->updated_at,
                                ]);
                                // saveQuietly: skip the provider-stamp + inbound
                                // translation observers (provider set above; no
                                // HTTP translate churn during a bulk backfill).
                                $im->saveQuietly();
                            }
                        });
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('inbox_messages')) {
            return;
        }
        // Only remove rows this migration created.
        \App\Models\InboxMessage::query()
            ->where('meta->widget_backfill', true)
            ->forceDelete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill — copy existing chat-origin rows from `messages`
 * into `inbox_messages` so historical team-inbox conversations don't
 * appear empty after the cutover.
 *
 * `messages` is left intact (campaigns / broadcasts / scheduled
 * still read from it). The chat rows are duplicated rather than
 * moved so nothing breaks if a stale `/chat` request still hits
 * the old table during the transition.
 *
 * Uses INSERT … SELECT so we don't have to load 100k+ encrypted
 * rows into PHP memory. Encrypted columns stay encrypted (same
 * key, same algorithm, same encoding).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('inbox_messages')->count() > 0;
        if ($exists) {
            // Already backfilled — don't double-insert.
            return;
        }

        // Subset of conversations that belong to the team-inbox surface.
        $chatConvIds = DB::table('conversations')->where('origin', 'chat')->pluck('id');
        if ($chatConvIds->isEmpty()) return;

        // Chunk the source so we don't blow up memory on huge installs.
        DB::table('messages')
            ->whereIn('conversation_id', $chatConvIds)
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $now = now();
                $batch = $rows->map(function ($r) use ($now) {
                    return [
                        'id'              => $r->id, // keep ids stable so wa_message_id meta still matches
                        'conversation_id' => $r->conversation_id,
                        'user_id'         => $r->user_id,
                        'agent_id'        => $r->agent_id ?? null,
                        'contact_id'      => $r->contact_id ?? null,
                        'template_id'     => $r->template_id ?? null,
                        'direction'       => $r->direction,
                        'to_number'       => $r->to_number,
                        'from_number'     => $r->from_number,
                        'body'            => $r->body,
                        'media_path'      => $r->media_path,
                        'media_type'      => $r->media_type,
                        'latitude'        => $r->latitude,
                        'longitude'       => $r->longitude,
                        'status'          => $r->status,
                        'failure_reason'  => $r->failure_reason,
                        'pinned'          => $r->pinned ?? false,
                        'starred'         => $r->starred ?? false,
                        'reaction'        => $r->reaction ?? null,
                        'quality_score'   => $r->quality_score ?? null,
                        'quality_note'    => $r->quality_note ?? null,
                        'meta'            => $r->meta,
                        'sent_at'         => $r->sent_at,
                        'delivered_at'    => $r->delivered_at,
                        'read_at'         => $r->read_at,
                        'created_at'      => $r->created_at ?: $now,
                        'updated_at'      => $r->updated_at ?: $now,
                    ];
                })->toArray();
                DB::table('inbox_messages')->insertOrIgnore($batch);
            });
    }

    public function down(): void
    {
        // Truncate (irreversible cleanup) — fresh runs of up() will
        // backfill again. Wrapped because TRUNCATE on a referenced
        // table fails when FKs exist; falls back to DELETE.
        try {
            DB::table('inbox_messages')->truncate();
        } catch (\Throwable $e) {
            DB::table('inbox_messages')->delete();
        }
    }
};

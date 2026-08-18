<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-stamp `messages.provider` and `inbox_messages.provider` from their
 * parent `conversations.provider`. Both auto-stamps previously resolved
 * engine via WorkspaceEngine::for($workspace_id) which falls back to the
 * platform default (`system_settings.default_send_method`) when no
 * WaProviderConfig exists for that workspace.
 *
 * Result: a workspace that runs Baileys but has no explicit provider
 * config got every message stamped 'waba' (because platform default is
 * 'waba'), even though the dispatcher actually routed the send through
 * Baileys. Dashboard engine filters then matched zero of those rows
 * when the workspace was on Baileys, and matched ALL of them when the
 * workspace switched to WABA — exact opposite of intended behaviour.
 *
 * The parent Conversation row has the correct provider (it was stamped
 * via the explicit migration 2026_05_26_140000 from device_id and the
 * inbound webhook handlers). Use it as the source of truth.
 *
 * Safe to re-run — overwrites with the conversation's value each time.
 * If the future fix to auto-stamp logic ever lands, this migration
 * still produces the right result.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('inbox_messages', 'provider') && Schema::hasColumn('conversations', 'provider')) {
            // UPDATE inbox_messages SET provider = conversations.provider
            //   WHERE conversations.id = inbox_messages.conversation_id
            DB::statement('
                UPDATE inbox_messages
                INNER JOIN conversations ON conversations.id = inbox_messages.conversation_id
                SET inbox_messages.provider = conversations.provider
                WHERE inbox_messages.provider <> conversations.provider
            ');
        }

        if (Schema::hasColumn('messages', 'provider') && Schema::hasColumn('conversations', 'provider')) {
            DB::statement('
                UPDATE messages
                INNER JOIN conversations ON conversations.id = messages.conversation_id
                SET messages.provider = conversations.provider
                WHERE messages.provider <> conversations.provider
                  AND conversations.provider IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        // No-op — we don't track the prior incorrect stamp values.
    }
};

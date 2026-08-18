<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ONE THREAD PER NUMBER — the storage half.
 *
 * The duplicate-chat bug had a single root cause: the customer's number was
 * written in three different shapes ('919…', '919…@s.whatsapp.net', '919…@lid')
 * and every lookup compared raw strings, so a thread saved in one shape was
 * invisible to code searching in another and a second thread got created.
 *
 * `contact_digits` is the normalised identity — digits only, no suffix — kept in
 * sync automatically by Conversation::saving(). Every lookup now matches on this
 * one column, so shape can never fork a thread again.
 *
 * The index is (workspace_id, contact_digits): every lookup is scoped to a
 * workspace, and `raw_jid` was never indexed at all, so this also turns a table
 * scan on every inbound message into an index seek.
 *
 * NOT unique on purpose. Workspaces upgrading from an older build already hold
 * duplicate rows, and a unique index would abort the migration on their data.
 * Team Inbox > Merge duplicate chats cleans those up; the resolver prevents new
 * ones regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('conversations', 'contact_digits')) {
            Schema::table('conversations', function (Blueprint $t) {
                $t->string('contact_digits', 32)->nullable()->after('alt_jid');
                $t->index(['workspace_id', 'contact_digits'], 'conv_ws_digits_idx');
            });
        }

        // Backfill. Strip any '@suffix' first, then non-digits, so
        // '919…@s.whatsapp.net' and '919…' both land on '919…'.
        //
        // Group chats ('@g.us') are namespaced 'g:<id>' so a group can never
        // collide with a DM. Widget threads carry a 'widget-…' raw_jid with no
        // real number and are left NULL — they are keyed by visitor, not phone.
        DB::statement("
            UPDATE conversations
               SET contact_digits = CASE
                   WHEN raw_jid IS NULL OR raw_jid = '' THEN NULL
                   WHEN raw_jid LIKE 'widget-%' THEN NULL
                   WHEN raw_jid LIKE '%@g.us' THEN CONCAT('g:', REGEXP_REPLACE(SUBSTRING_INDEX(raw_jid, '@', 1), '[^0-9]', ''))
                   ELSE NULLIF(REGEXP_REPLACE(SUBSTRING_INDEX(raw_jid, '@', 1), '[^0-9]', ''), '')
               END
             WHERE contact_digits IS NULL
        ");

        // Rows that only ever had alt_jid populated.
        DB::statement("
            UPDATE conversations
               SET contact_digits = NULLIF(REGEXP_REPLACE(SUBSTRING_INDEX(alt_jid, '@', 1), '[^0-9]', ''), '')
             WHERE contact_digits IS NULL
               AND alt_jid IS NOT NULL AND alt_jid <> ''
               AND alt_jid NOT LIKE 'widget-%'
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('conversations', 'contact_digits')) {
            Schema::table('conversations', function (Blueprint $t) {
                $t->dropIndex('conv_ws_digits_idx');
                $t->dropColumn('contact_digits');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Any inbound message" trigger — the DEFAULT ROUTE of the keyword matcher.
 *
 * Until now the only way to express "run on every message" was to type `*` or
 * `.*` into the keyword box, which `Flow::syncKeywordTriggerReply()` turned
 * into a regex rule. Three problems with that:
 *
 *   1. It is a magic string living in the same column real keywords are
 *      compared against, so a customer literally typing `.*` collides with it.
 *   2. It runs a preg_match on EVERY inbound forever, for a pattern whose
 *      answer is always true.
 *   3. Worst: it sits in the same candidate pile as real keywords, and the
 *      matcher has no ordering — so whichever rule the database returned first
 *      (in practice the lowest id, i.e. whichever flow was saved first) won.
 *      A catch-all saved before a `book` flow silently swallowed it.
 *
 * Making it a real column turns "is this the default route?" into a fact we can
 * index, sort, count and constrain, instead of a string we have to sniff. The
 * matcher then treats it as a separate TIER — consulted only after every
 * keyword tier has failed — so a default route can no longer outrank a keyword.
 *
 * Existing rows default to 0 (a normal keyword rule), so behaviour is unchanged
 * until an operator explicitly picks "Any message".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_replies', function (Blueprint $t) {
            if (!Schema::hasColumn('keyword_replies', 'is_catch_all')) {
                $t->boolean('is_catch_all')->default(false)->after('matching_method');
                // The lookup is always "the active default route for this
                // workspace + device", so index exactly that.
                $t->index(['workspace_id', 'device_id', 'is_catch_all', 'status'], 'kwr_catchall_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('keyword_replies', function (Blueprint $t) {
            if (Schema::hasColumn('keyword_replies', 'is_catch_all')) {
                $t->dropIndex('kwr_catchall_idx');
                $t->dropColumn('is_catch_all');
            }
        });
    }
};

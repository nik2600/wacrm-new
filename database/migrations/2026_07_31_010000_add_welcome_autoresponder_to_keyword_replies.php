<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto Responder / Welcome Message (client Mahmoud Ashraf spec, 2026-07-31).
 *
 * Turns the keyword-only auto-reply into a full auto-responder WITHOUT touching
 * how existing keyword rules behave. Every column here is nullable / defaulted
 * so a pre-existing row keeps its exact current behaviour:
 *   - trigger_type defaults to 'keyword' (the classic keyword match)
 *   - every new gate (excluded / working-hours / agent-override / resend) is a
 *     no-op when its column is empty.
 *
 * The NEW "Welcome Message" trigger is simply trigger_type='welcome': it fires
 * once when a customer starts a NEW conversation (first inbound), no keyword
 * needed. Resend/agent-override/working-hours apply to BOTH welcome and keyword
 * rules (the client's Step 2/3 explicitly says "these rules work for both").
 *
 * Idempotent (hasColumn guards) so the resilient updater can re-run it safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_replies', function (Blueprint $t) {
            // keyword | welcome. 'welcome' fires on the first message of a new
            // conversation instead of matching the keyword column.
            if (!Schema::hasColumn('keyword_replies', 'trigger_type')) {
                $t->string('trigger_type', 16)->default('keyword')->after('is_catch_all')->index();
            }

            // Step 2 — Resend. The canonical throttle stays in `cooldown`
            // (seconds); `resend_unit` only round-trips the UI's Minutes/Hours/
            // Days choice so the form can re-render the value the operator typed.
            if (!Schema::hasColumn('keyword_replies', 'resend_unit')) {
                $t->string('resend_unit', 12)->nullable()->after('cooldown');
            }

            // Step 2 — Agent override. When true, a human agent reply pauses this
            // rule for the contact; it resumes after `resume_after` seconds of
            // customer inactivity (resume_unit is the UI display unit only).
            if (!Schema::hasColumn('keyword_replies', 'stop_on_agent_reply')) {
                $t->boolean('stop_on_agent_reply')->default(false)->after('resend_unit');
            }
            if (!Schema::hasColumn('keyword_replies', 'resume_after')) {
                $t->unsignedInteger('resume_after')->nullable()->after('stop_on_agent_reply');
            }
            if (!Schema::hasColumn('keyword_replies', 'resume_unit')) {
                $t->string('resume_unit', 12)->nullable()->after('resume_after');
            }

            // Step 3 — per-rule Working Hours. JSON shape:
            //   { "enabled": true,
            //     "days": ["mon","tue",...],       // active business days
            //     "from": "09:00", "to": "18:00",  // 24h local time
            //     "timezone": "Asia/Riyadh",
            //     "outside_action": "send"|"none" } // send outside-hours msg or nothing
            // null = always active (unchanged legacy behaviour).
            if (!Schema::hasColumn('keyword_replies', 'working_hours')) {
                $t->json('working_hours')->nullable()->after('timeout');
            }

            // Step 1 — Excluded recipients. Arrays of E.164 digit strings and of
            // contact-group ids; a contact in either is skipped by this rule.
            if (!Schema::hasColumn('keyword_replies', 'excluded_numbers')) {
                $t->json('excluded_numbers')->nullable()->after('working_hours');
            }
            if (!Schema::hasColumn('keyword_replies', 'excluded_group_ids')) {
                $t->json('excluded_group_ids')->nullable()->after('excluded_numbers');
            }
        });

        Schema::table('keyword_reply_contents', function (Blueprint $t) {
            // 'primary' (the normal reply) | 'outside_hours' (the message sent when
            // working-hours outside_action='send'). Lets the outside-hours composer
            // reuse the SAME content table + media pipeline the primary reply uses.
            if (!Schema::hasColumn('keyword_reply_contents', 'variant_role')) {
                $t->string('variant_role', 16)->default('primary')->after('content_type')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('keyword_replies', function (Blueprint $t) {
            foreach ([
                'trigger_type', 'resend_unit', 'stop_on_agent_reply', 'resume_after',
                'resume_unit', 'working_hours', 'excluded_numbers', 'excluded_group_ids',
            ] as $col) {
                if (Schema::hasColumn('keyword_replies', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
        Schema::table('keyword_reply_contents', function (Blueprint $t) {
            if (Schema::hasColumn('keyword_reply_contents', 'variant_role')) {
                $t->dropColumn('variant_role');
            }
        });
    }
};

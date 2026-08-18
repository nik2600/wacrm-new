<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Send-time template overrides.
 *
 * A template's variable_map decides which contact attribute fills each
 * {{N}} slot. That is a property of the TEMPLATE, so changing what a
 * single campaign puts in {{1}} used to mean editing the template —
 * which then changed every other campaign using it.
 *
 * This column carries a per-send override that shadows the template's
 * map for that one send only. NULL / absent slot = today's behaviour,
 * so every existing row resolves exactly as before.
 *
 * Shape (see App\Services\TemplateOverrideResolver):
 *   {
 *     "header":  {"mode":"text|media","text":"...","media_url":"https://…"},
 *     "body":    ["Hi {{first_name}}", "SAVE20"],
 *     "footer":  ["..."],
 *     "buttons": [{"index":0,"sub_type":"url","value":"https://shop/{{order_id}}"}]
 *   }
 *
 * Values are TOKEN STRINGS — a literal, an {{attribute}} token, or a
 * mix. Operator-authored content that can carry customer data, so it
 * follows the existing encrypted:array pattern (custom_buttons,
 * target_numbers) and therefore needs a longText column, not json.
 */
return new class extends Migration
{
    /** table => column it should sit after */
    private array $targets = [
        'wpcampaigns'        => 'template_id_b',
        'broadcasts'         => 'template_id',
        'scheduled_messages' => 'template_type',
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $after) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'template_overrides')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $after) {
                $col = $t->longText('template_overrides')->nullable();  // encrypted:array
                if (Schema::hasColumn($table, $after)) {
                    $col->after($after);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->targets) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'template_overrides')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('template_overrides');
            });
        }
    }
};

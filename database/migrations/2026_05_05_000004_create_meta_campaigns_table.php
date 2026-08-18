<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta-Ads campaigns table — replaces the old project's
 * `campaigns` table from D:\wadesk_2806\New folder
 * (Campaign + AdSet + AdCreative + Ad split into 4 tables, all
 * tied together by `campaign_id`). Here we collapse the lot into
 * a single row per campaign, with the AdSet/Creative/Ad fields
 * embedded as JSON or scalar columns — most operators only run
 * one ad-set + creative per campaign, and the old multi-table
 * shape made every list query a 4-way join.
 *
 * PII fields (campaign name, creative copy, CTWA phone/message,
 * destination URL, targeting) are TEXT + `encrypted` cast on
 * the model so the DB is unreadable at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Public Meta IDs are not PII — kept plain so we can
            // index/search by them later when wiring the FB API.
            $table->string('facebook_id')->nullable()->index();

            // Encrypted-at-rest (operator-authored copy + targeting).
            $table->text('name');
            $table->text('creative_title')->nullable();
            $table->text('creative_body')->nullable();
            $table->text('creative_link_url')->nullable();
            $table->text('ctwa_phone')->nullable();
            $table->text('ctwa_message')->nullable();
            $table->longText('targeting')->nullable();   // JSON, encrypted-array

            // Plain-text categorical columns — used in WHERE / GROUP BY.
            $table->string('objective', 32)->default('OUTCOME_TRAFFIC');
            $table->string('optimization_goal', 32)->default('LINK_CLICKS');
            $table->string('status', 16)->default('PAUSED')->index();
            $table->string('type', 32)->default('campaign');
            $table->string('ctwa_cta', 32)->nullable();
            $table->boolean('ctwa_enabled')->default(false);

            // Numeric / metrics — not PII.
            $table->decimal('daily_budget', 12, 2)->default(0);
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->json('insights')->nullable();         // spend/clicks/etc
            $table->unsignedSmallInteger('ad_set_count')->default(1);
            $table->unsignedSmallInteger('ad_count')->default(1);

            $table->string('creative_image')->nullable();   // storage path

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'optimization_goal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_campaigns');
    }
};

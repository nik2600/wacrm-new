<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled Instagram posts gain a media TYPE so the composer can schedule
 * reels / stories / carousels (not just images), per the verified Content
 * Publishing API. `video_url` holds reel/story video; `media_urls` holds the
 * carousel item list (json array of {type,url}). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('instagram_scheduled_posts')) return;
        Schema::table('instagram_scheduled_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_scheduled_posts', 'media_type')) {
                $table->string('media_type', 16)->default('image')->after('instagram_account_id');
            }
            if (!Schema::hasColumn('instagram_scheduled_posts', 'video_url')) {
                $table->string('video_url', 1024)->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('instagram_scheduled_posts', 'media_urls')) {
                $table->json('media_urls')->nullable()->after('video_url');
            }
        });
        // image_url was NOT NULL for image-only posts; reels/stories/carousels
        // carry no image_url, so relax it.
        if (Schema::hasColumn('instagram_scheduled_posts', 'image_url')) {
            try {
                Schema::table('instagram_scheduled_posts', function (Blueprint $table) {
                    $table->string('image_url', 1024)->nullable()->change();
                });
            } catch (\Throwable $e) { /* dbal/driver may not support change(); non-fatal */ }
        }
    }

    public function down(): void
    {
        // Leave the columns; dropping would lose scheduled reels/stories/carousels.
    }
};

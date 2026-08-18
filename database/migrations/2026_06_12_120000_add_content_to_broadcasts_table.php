<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile-app "message queue" stores its content on the `broadcasts` row,
 * but the table only had `template_id` + `name` — so custom text, caption,
 * media, buttons and location were silently dropped at create time (queue
 * sent empty / buttons + image lost). These columns let a queue carry an
 * arbitrary Unofficial-API message, matching the old app's `messages` table.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            if (!Schema::hasColumn('broadcasts', 'temp_caption')) {
                $table->text('temp_caption')->nullable();        // body / caption (plaintext)
            }
            if (!Schema::hasColumn('broadcasts', 'template_type')) {
                $table->string('template_type', 40)->nullable(); // Plane-Text|Text-With-Media|Image-Only|Text-With-Location
            }
            if (!Schema::hasColumn('broadcasts', 'temp_image')) {
                $table->string('temp_image', 500)->nullable();   // stored media path/url
            }
            if (!Schema::hasColumn('broadcasts', 'button_text')) {
                $table->text('button_text')->nullable();         // buttons JSON
            }
            if (!Schema::hasColumn('broadcasts', 'latitude')) {
                $table->string('latitude', 32)->nullable();
            }
            if (!Schema::hasColumn('broadcasts', 'longitude')) {
                $table->string('longitude', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            foreach (['temp_caption', 'template_type', 'temp_image', 'button_text', 'latitude', 'longitude'] as $col) {
                if (Schema::hasColumn('broadcasts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

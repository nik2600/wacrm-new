<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store Meta's own sample-image URL for a media-header template.
 *
 * When a template is imported/synced from Meta, its GET response carries the
 * approved sample under `components[].example.header_handle[0]` — a downloadable
 * URL. We never captured it, so an imported IMAGE-header template had no image
 * to send and every send failed Meta's 132012 format check. Storing it lets the
 * sender fetch the sample and upload it to Meta for a media id at send time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_templates', 'header_sample_url')) {
                $table->text('header_sample_url')->nullable()->after('attachment_file');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            if (Schema::hasColumn('wa_templates', 'header_sample_url')) {
                $table->dropColumn('header_sample_url');
            }
        });
    }
};

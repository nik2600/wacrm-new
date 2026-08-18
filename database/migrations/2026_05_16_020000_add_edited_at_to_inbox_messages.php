<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track the edit time on each inbox message so the UI can show
 * "Edited HH:MM" next to the timestamp (matching WhatsApp's UX).
 * Null = never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('reaction');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_messages', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};

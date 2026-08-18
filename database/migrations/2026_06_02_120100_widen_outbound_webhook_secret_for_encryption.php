<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The OutboundWebhook.secret column was varchar(128) — sized for a plaintext
 * HMAC key. Now that the model encrypts it (Laravel `encrypted` cast), the
 * ciphertext is ~200-260 chars, so the column must be TEXT or inserts fail
 * with "Data too long". (The sibling Webhook model already uses TEXT.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('outbound_webhooks', 'secret')) {
            Schema::table('outbound_webhooks', function (Blueprint $table) {
                $table->text('secret')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Leave as TEXT — narrowing could truncate ciphertext.
    }
};

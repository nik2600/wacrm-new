<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jessica #3 — keep the customer's ORIGINAL voice note / photo with the order
 * so the merchant can replay/inspect it from the order view (the AI transcript
 * is handy, but a human may want to hear/see the source). The pending cart
 * carries it; on confirm it's copied into wa_orders.meta_json (already JSON, no
 * schema change there).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wa_pending_orders')) return;
        Schema::table('wa_pending_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('wa_pending_orders', 'order_media_path'))       $t->string('order_media_path')->nullable()->after('group_code');
            if (!Schema::hasColumn('wa_pending_orders', 'order_media_type'))       $t->string('order_media_type', 16)->nullable()->after('order_media_path');
            if (!Schema::hasColumn('wa_pending_orders', 'order_media_transcript')) $t->text('order_media_transcript')->nullable()->after('order_media_type');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('wa_pending_orders')) return;
        Schema::table('wa_pending_orders', function (Blueprint $t) {
            foreach (['order_media_path', 'order_media_type', 'order_media_transcript'] as $c) {
                if (Schema::hasColumn('wa_pending_orders', $c)) $t->dropColumn($c);
            }
        });
    }
};

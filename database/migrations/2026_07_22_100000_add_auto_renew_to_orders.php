<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-purchase auto-renew choice.
 *
 * Auto-renewal was previously all-or-nothing: every eligible monthly purchase
 * became a recurring subscription (shouldRecur() read only global config +
 * gateway capability, never the customer). This column records the customer's
 * choice at checkout so a one-time buyer isn't re-billed next cycle.
 *
 * Default TRUE preserves today's behaviour for anything that doesn't set it
 * (existing rows, API/offline paths) — the toggle is opt-OUT, not opt-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'auto_renew')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true)->after('billing_period');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'auto_renew')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('auto_renew');
            });
        }
    }
};

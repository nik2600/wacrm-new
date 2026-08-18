<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on packages + data retention.
 *
 * 1) packages.type — 'plan' (a full subscription, the default/existing) vs
 *    'addon' (an à-la-carte feature pack a customer buys ON TOP of their plan,
 *    e.g. "+1 WhatsApp number" or "Campaigns add-on"). An add-on reuses the
 *    SAME feature-flag + limit columns a plan has; whatever toggles/limits it
 *    carries are merged onto the customer's base plan at resolve time.
 * 2) packages.data_retention_days — when a workspace's plan has been expired
 *    (inactive) for this many days, ALL its data is auto-wiped. 0 = never.
 * 3) workspace_addons — which add-ons a workspace has bought + their window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'type')) {
                $table->string('type', 16)->default('plan')->after('plan_id')->index();
            }
            if (!Schema::hasColumn('packages', 'data_retention_days')) {
                // Days AFTER plan expiry before the workspace's data is wiped.
                // 0 = never auto-wipe (safe default for every existing plan).
                $table->unsignedInteger('data_retention_days')->default(0)->after('lifetime');
            }
        });

        if (!Schema::hasTable('workspace_addons')) {
            Schema::create('workspace_addons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('package_id')->index();   // the add-on package
                $table->unsignedBigInteger('order_id')->nullable();  // purchase order, if any
                $table->string('status', 16)->default('active');     // active | cancelled | expired
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();            // null = lifetime / until cancelled
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_addons');
        Schema::table('packages', function (Blueprint $table) {
            foreach (['type', 'data_retention_days'] as $col) {
                if (Schema::hasColumn('packages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

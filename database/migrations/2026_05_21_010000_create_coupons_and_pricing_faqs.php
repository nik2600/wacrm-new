<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make pricing + checkout fully dynamic:
 *   • coupons table — real codes admin can create + validate at checkout
 *   • pricing_faqs table — admin-editable FAQ accordions on /pricing
 *
 * Tax rate, yearly-discount %, country list, and "What's accepted" hint
 * stay in the existing SystemSetting K/V (see CommonSettingsSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description', 255)->nullable();
            // 'percent' = X% off subtotal · 'fixed' = X off subtotal (in currency)
            $table->string('type', 16)->default('percent');
            $table->decimal('amount', 12, 4)->default(0);
            $table->decimal('min_order_amount', 12, 4)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->json('applicable_package_ids')->nullable();   // null = any plan
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('pricing_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 255);
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_faqs');
        Schema::dropIfExists('coupons');
    }
};

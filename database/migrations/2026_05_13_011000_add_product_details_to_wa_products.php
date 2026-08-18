<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beef up wa_products with the fields a real customer-facing
 * storefront needs: sale pricing, weight for shipping, category +
 * tags for filtering, long-form HTML body for the product page,
 * and a draft/published lifecycle.
 *
 * All nullable so existing rows keep working; defaults match what
 * a customer-storefront would expect (published, taxable, available).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_products', function (Blueprint $table) {
            // Compare-at / strikethrough price for sales. Stored in
            // minor units like price_minor (paise / cents). NULL means
            // "not on sale".
            $table->unsignedBigInteger('compare_price_minor')->nullable()->after('price_minor');

            // Shipping helpers.
            $table->unsignedInteger('weight_grams')->nullable()->after('compare_price_minor');

            // Organization for filtering / sidebar nav on the storefront.
            $table->string('category', 96)->nullable()->after('weight_grams');
            $table->json('tags_json')->nullable()->after('category');

            // Long-form description / product page body (HTML allowed).
            // Keeps the existing `description` column as the short
            // summary used in cards + WhatsApp deep links.
            $table->longText('body_html')->nullable()->after('description');

            // Lifecycle. 'active' = visible on storefront, 'draft' =
            // hidden but editable, 'archived' = soft hide.
            $table->string('status', 16)->default('active')->after('tags_json');

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('wa_products', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'status']);
            $table->dropIndex(['workspace_id', 'category']);
            $table->dropColumn([
                'compare_price_minor', 'weight_grams', 'category',
                'tags_json', 'body_html', 'status',
            ]);
        });
    }
};

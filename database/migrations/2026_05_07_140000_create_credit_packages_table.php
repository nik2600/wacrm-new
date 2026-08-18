<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-curated credit bundles. Separate from `packages` (which is the
 * SaaS subscription-plan table) — credit packs are one-time top-ups
 * with a flat "X rupees buys Y credits" offer the admin can tune
 * without touching code, while subscriptions are the long-running
 * monthly/yearly thing with feature gates.
 *
 * Why store price as minor units (paise / cents) and a currency code:
 *   - integer arithmetic, no float drift on totals
 *   - same shape as wallet_transactions.amount for currency-kind rows
 *   - dual-currency packages later (admin offers ₹ + $ tiers) just
 *     means filtering by currency at checkout time.
 *
 * Seeded with three sensible defaults (₹100=5,000 / ₹500=27,500 /
 * ₹1,000=60,000). Admin can disable / reprice them from the UI.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 96);
            $table->string('slug', 96)->unique();
            $table->unsignedBigInteger('price_minor');           // paise / cents
            $table->string('currency_code', 3)->default('INR');
            $table->unsignedBigInteger('credits');
            $table->string('badge', 32)->nullable();             // e.g. "Most popular", "Best value"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Seed three default tiers so the admin UI has something to
        // show right after migrate, and the user-side wallet has at
        // least some packages to render before the admin ever logs in.
        // Prices are paise (₹100 = 10000 paise).
        \DB::table('credit_packages')->insert([
            [
                'name'         => 'Starter',
                'slug'         => 'starter',
                'price_minor'  => 10000,    // ₹100
                'currency_code'=> 'INR',
                'credits'      => 5000,
                'badge'        => null,
                'description'  => '5,000 message credits — perfect for trial sends.',
                'is_active'    => true,
                'is_featured'  => false,
                'sort_order'   => 10,
                'created_at'   => now(), 'updated_at' => now(),
            ],
            [
                'name'         => 'Growth',
                'slug'         => 'growth',
                'price_minor'  => 50000,    // ₹500
                'currency_code'=> 'INR',
                'credits'      => 27500,
                'badge'        => 'Most popular',
                'description'  => '27,500 credits — 10% bonus over starter rate.',
                'is_active'    => true,
                'is_featured'  => true,
                'sort_order'   => 20,
                'created_at'   => now(), 'updated_at' => now(),
            ],
            [
                'name'         => 'Scale',
                'slug'         => 'scale',
                'price_minor'  => 100000,   // ₹1,000
                'currency_code'=> 'INR',
                'credits'      => 60000,
                'badge'        => 'Best value',
                'description'  => '60,000 credits — 20% bonus, ideal for active campaigns.',
                'is_active'    => true,
                'is_featured'  => false,
                'sort_order'   => 30,
                'created_at'   => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_packages');
    }
};

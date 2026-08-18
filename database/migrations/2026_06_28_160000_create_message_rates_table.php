<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-country × category message credit rates.
 *
 * Replaces the single flat `credits_per_message` SystemSetting with a fair,
 * per-destination model that mirrors how Meta itself bills (a US marketing
 * message costs ~12× an India one; utility ≪ marketing; service-window
 * replies are free). Credits are integers — admin sets whole credits per
 * (country, category); 0 = free. Falls back to the flat setting when no row
 * matches OR when the `per_country_credits_enabled` flag is OFF, so existing
 * installs keep their current behaviour until they opt in.
 *
 * Wildcard rows: country_code = '' means "any country"; category = '' means
 * "any category". Resolver prefers the most specific match.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('message_rates')) {
            Schema::create('message_rates', function (Blueprint $table) {
                $table->id();
                $table->string('country_code', 2)->default('');   // ISO-3166 alpha-2, '' = any
                $table->string('category', 20)->default('');       // marketing|utility|authentication|service|'' = any
                $table->unsignedInteger('credits')->default(1);    // whole credits per message; 0 = free
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['country_code', 'category']);
                $table->index('country_code');
            });
        }

        // Starter rates — RELATIVE to India = 1 (admin edits in /admin/settings/
        // wallet-rules). Values track Meta's rough cost ratios circa 2026.
        $seed = [
            // global default + free service window
            ['', '',               1],
            ['', 'service',         0],
            ['', 'utility',         1],
            ['', 'authentication',  1],
            // India (baseline)
            ['IN', 'marketing',      1],
            ['IN', 'utility',        1],
            ['IN', 'authentication', 1],
            // United States
            ['US', 'marketing',     12],
            ['US', 'utility',        2],
            ['US', 'authentication', 6],
            // United Kingdom
            ['GB', 'marketing',     12],
            ['GB', 'utility',        2],
            ['GB', 'authentication', 6],
            // Brazil
            ['BR', 'marketing',      6],
            // UAE
            ['AE', 'marketing',      5],
            // Mexico
            ['MX', 'marketing',      3],
        ];
        $now = now();
        foreach ($seed as [$cc, $cat, $credits]) {
            DB::table('message_rates')->updateOrInsert(
                ['country_code' => $cc, 'category' => $cat],
                ['credits' => $credits, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        // Flag `per_country_credits_enabled` is intentionally NOT seeded here —
        // it defaults to '0' (OFF) via SystemSetting::get(...,'0'), so existing
        // installs keep flat billing until the admin flips it on. The admin
        // toggle persists it through the model (correct casts/encryption).
    }

    public function down(): void
    {
        Schema::dropIfExists('message_rates');
    }
};

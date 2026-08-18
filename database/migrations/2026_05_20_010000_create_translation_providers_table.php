<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 8 — translation provider catalog. Same shape as
 * payment_gateways: one row per provider, pre-seeded by
 * TranslationProviderSeeder, admin configures + activates from
 * /admin/translation-providers.
 *
 * Credentials TEXT column holds Crypt::encryptString(json) so an
 * accidental DB dump doesn't leak API keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->text('credentials')->nullable();        // encrypted JSON
            $table->json('extra_config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_default', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_providers');
    }
};

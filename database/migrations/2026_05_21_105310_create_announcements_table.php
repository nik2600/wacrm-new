<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide announcement marquee. Admin-managed. Rendered as a
 * horizontal-scrolling top bar above the user-dashboard header on
 * every authenticated page when at least one row is active +
 * inside its starts_at / expires_at window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('text', 500);
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 64)->nullable();
            // info | promo | warning | success — controls the bar tone.
            $table->string('tone', 16)->default('info');
            $table->boolean('is_active')->default(true);
            $table->boolean('dismissible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index(['starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

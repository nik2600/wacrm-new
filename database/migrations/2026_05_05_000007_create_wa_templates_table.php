<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp template library — ports the old project's
 * D:\wadesk_2806\New folder\app\Http\Controllers\TemplatesWaDeskController.php
 * onto the new Eloquent + encrypted-at-rest pattern.
 *
 * Two categorisation columns:
 *   - `category`      → industry bucket the UI groups by
 *                       (travel / healthcare / education / e-commerce
 *                       / festival / finance / utility). Plain so
 *                       the sidebar tabs can WHERE on it.
 *   - `meta_category` → Meta's Marketing / Authentication / Utility
 *                       classification, stored separately because
 *                       it drives template approval, not UI tabs.
 *
 * Operator-authored copy (header, body, footer, buttons,
 * carousel_data, rejection_reason) is encrypted-at-rest with the
 * same `encrypted` cast the Contact + Conversation models use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Encrypted-at-rest — operator-authored.
            $table->text('template_name');
            $table->text('header')->nullable();
            $table->text('template_body');
            $table->text('footer')->nullable();
            $table->longText('buttons')->nullable();         // encrypted JSON
            $table->longText('carousel_data')->nullable();   // encrypted JSON
            $table->longText('variable_map')->nullable();    // encrypted JSON
            $table->text('rejection_reason')->nullable();

            // Plain categorical columns — used in WHERE / GROUP BY.
            $table->string('category', 32)->default('utility')->index();
            $table->string('meta_category', 32)->nullable();
            $table->string('template_type', 32)->default('standard');
            $table->string('attachment_type', 16)->nullable();
            $table->string('attachment_file')->nullable();
            $table->string('language', 16)->default('en_US');
            $table->string('status', 16)->default('pending')->index();

            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
    }
};

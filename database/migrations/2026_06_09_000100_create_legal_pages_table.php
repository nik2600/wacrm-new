<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legal pages (Terms / Privacy / Refund / Cookie / Acceptable Use) become
 * fully admin-editable instead of hardcoded in Blade. Every field the public
 * <x-frontend.legal-page> renders lives here: title, subtitle, the two date
 * labels, and the ordered sections array (each: n / title / body HTML).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();           // terms|privacy|refund|cookies|acceptable-use
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('updated_label')->nullable(); // e.g. "March 14, 2026"
            $table->string('effective_label')->nullable();
            $table->longText('sections')->nullable();    // JSON: [{n,title,body}, ...]
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_pages');
    }
};

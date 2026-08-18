<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content store for the public marketing site live editor.
 *
 * Flat key/value: `ckey` is a dotted content key — e.g.
 *   home.hero.headline   (a section field)
 *   theme.wa.deep        (a theme color token)
 *   home.__layout        (a page's section order/visibility, json)
 *
 * Two value columns: `draft` is the editor's working copy; `published`
 * is what the public site renders. "Publish" copies draft -> published.
 * A null value means "use the hardcoded default" (the 2nd arg of fc()),
 * so the table starts EMPTY and the site renders exactly as shipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_content', function (Blueprint $table) {
            $table->id();
            $table->string('ckey')->unique();          // dotted content key
            $table->string('type', 24)->default('text'); // text|richtext|color|image|url|bool|json
            $table->longText('draft')->nullable();       // editor working value
            $table->longText('published')->nullable();   // live value
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_content');
    }
};

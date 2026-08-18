<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidebook_articles', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 160)->unique();
            $t->string('title', 200);
            $t->string('category', 80)->default('general');
            $t->text('excerpt')->nullable();   // 1-line summary shown in list
            $t->longText('body')->nullable();   // Markdown
            $t->boolean('is_published')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedInteger('views_count')->default(0);
            $t->unsignedInteger('helpful_count')->default(0);
            $t->unsignedInteger('not_helpful_count')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['category', 'is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidebook_articles');
    }
};

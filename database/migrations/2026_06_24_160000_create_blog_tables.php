<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing blog — admin authors posts that render on the public frontend
 * (/blog + /blog/{slug}) with full per-post SEO (meta, OG, canonical, JSON-LD)
 * and feed the sitemap.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('blog_categories')) {
            Schema::create('blog_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('description', 500)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('blog_posts')) {
            Schema::create('blog_posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('excerpt', 500)->nullable();
                $table->longText('body')->nullable();
                $table->string('featured_image')->nullable();   // media_disk() path
                $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOndelete();
                $table->json('tags')->nullable();
                $table->string('author_name')->nullable();
                $table->string('status')->default('draft');       // draft | published
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('views')->default(0);
                $table->boolean('is_featured')->default(false);

                // Per-post SEO
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->string('meta_keywords')->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('noindex')->default(false);

                $table->timestamps();

                $table->index(['status', 'published_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};

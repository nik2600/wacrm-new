<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts table — ported from old project (D:\wadesk_2806\New folder).
 *
 * The original old-project migration was bare (`name`, `email`, `subject`,
 * `mobile`, `msg`). The full schema (title/first_name/last_name/etc.) was
 * presumably layered on later via ad-hoc ALTERs. We consolidate everything
 * the model+controller actually use into a single CREATE here.
 *
 * TODO(auth): user_id is left nullable + un-indexed on the users FK because
 *             the auth/user seeding flow isn't wired in this project yet.
 *             Once auth lands, add ->constrained()->cascadeOnDelete().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('title')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('name')->nullable();

            $table->string('language')->nullable();
            $table->text('address')->nullable();

            // JSON list of group ids (matches old `contact_group => array` cast)
            $table->longText('contact_group')->nullable();

            $table->text('email')->nullable();
            $table->string('country_code')->nullable();
            $table->text('mobile')->nullable();

            $table->text('msg')->nullable();
            $table->text('subject')->nullable();
            $table->string('image')->nullable();

            $table->boolean('is_unsubscribed')->default(false);
            $table->json('custom_attributes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact groups table — ported from the old `contacts_group` table.
 *
 * The new project uses the more standard table name `contact_groups`
 * (singular -> plural, snake_case). The model overrides `$table` so the
 * old-style controller queries still work.
 *
 * TODO(auth): user_id left nullable & not FK-constrained until auth wired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_group')->nullable();
            $table->longText('note')->nullable();
            $table->boolean('status')->default(true);
            $table->string('color', 32)->nullable(); // accent color (UI nicety)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_groups');
    }
};

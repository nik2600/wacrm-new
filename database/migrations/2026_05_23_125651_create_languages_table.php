<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('code', 12)->unique();
            $t->string('native_name', 80)->nullable();
            $t->enum('direction', ['ltr', 'rtl'])->default('ltr');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};

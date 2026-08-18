<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('attribute_name');                  // "Order ID"
            $table->string('attribute_key');                   // "order_id" — the slash-search key
            $table->string('attribute_value')->nullable();     // optional default
            $table->text('description')->nullable();
            $table->string('type', 16)->default('custom');     // custom | system
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'attribute_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name')->nullable();        // Internal label
            $table->string('environment')->nullable(); // Production / Staging / etc.
            $table->string('http_method', 8)->default('POST');
            $table->text('webhook_url');               // encrypted
            $table->text('secret')->nullable();        // encrypted (for HMAC)
            $table->text('events')->nullable();        // encrypted JSON array
            $table->boolean('status')->default(true);  // active / paused
            $table->boolean('is_failing')->default(false);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('last_status_code')->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->timestamp('last_fired_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('icon_color', 24)->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_id')->index();
            $table->string('event_name');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('is_retry')->default(false);
            $table->text('payload')->nullable();        // encrypted
            $table->text('response_body')->nullable();  // encrypted
            $table->text('error')->nullable();
            $table->timestamp('fired_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};

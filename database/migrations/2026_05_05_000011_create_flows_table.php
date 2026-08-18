<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->text('flow_name');                     // encrypted
            $table->longText('flow_data')->nullable();     // encrypted JSON {flowNodes, flowEdges}
            $table->string('flow_file_path')->nullable();  // optional uploads/flows/flow_{id}.json mirror
            $table->string('category', 64)->nullable()->index(); // welcome / cart / post-purchase / re-engagement / event / lead
            $table->boolean('is_published')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('flow_connected_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flow_id')->index();
            $table->text('device_number');                 // encrypted
            $table->text('device_name')->nullable();       // encrypted
            $table->string('status', 16)->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index(['flow_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_connected_devices');
        Schema::dropIfExists('flows');
    }
};

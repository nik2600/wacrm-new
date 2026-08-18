<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name', 64);
            $table->string('slug', 64);
            $table->string('color', 16)->default('#075E54');
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('default_assignee_user_id')->nullable();
            $table->string('assignment_strategy', 16)->default('manual');
            // manual | round_robin | least_loaded | sticky

            $table->json('business_hours')->nullable();
            // { mon:[{start:"09:00",end:"18:00"}], tue:[...] }

            $table->string('timezone', 64)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'is_default']);
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->boolean('is_lead')->default(false);
            $table->unsignedInteger('capacity')->default(20);
            // max concurrent open conversations the engine will hand to this user
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
};

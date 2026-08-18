<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name', 64);
            $table->string('slug', 64);
            $table->string('color', 16)->default('#075E54');
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('conversation_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'tag_id']);
        });

        // Routing rules engine. Conditions evaluated in `sort` order on inbound;
        // first match wins (or all match if `stop_on_match` is false).
        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name', 128);
            $table->json('conditions');
            // [{ field: "message_text", op: "contains", value: "refund", case: "i" }, ...]
            $table->json('actions');
            // [{ type: "assign_team", team_id: 4 }, { type: "set_priority", value: "high" }, ...]
            $table->boolean('stop_on_match')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('fired_count')->default(0);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active', 'sort']);
        });

        // SLA policies — one workspace can have several (e.g. VIP / standard).
        // Conversations point at one via conversations.sla_policy_id.
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name', 128);
            $table->unsignedInteger('first_response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->boolean('pause_when_waiting_on_customer')->default(true);
            $table->boolean('respect_business_hours')->default(true);
            $table->json('priority_overrides')->nullable();
            // { urgent:{ first_response_minutes:15, resolution_minutes:240 }, ... }
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'is_default']);
        });

        Schema::create('saved_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            // null = workspace-wide, non-null = personal
            $table->string('shortcut', 64)->index();
            // typed as /shortcut in composer
            $table->string('title', 128);
            $table->text('body');
            // encrypted at rest
            $table->json('attachments')->nullable();
            $table->string('category', 64)->nullable();
            $table->unsignedBigInteger('used_count')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('key', 64);
            $table->string('label', 128);
            $table->string('type', 16)->default('text');
            // text | number | date | select | bool | url | email
            $table->json('options')->nullable();
            // for select type
            $table->boolean('required')->default(false);
            $table->boolean('show_in_panel')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_custom_fields');
        Schema::dropIfExists('saved_replies');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('conversation_tag');
        Schema::dropIfExists('tags');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('plan_id')->unique();
            $table->string('pname');
            $table->string('pfeatures_id')->nullable();   // CSV of package_features.id
            $table->string('plan_unit')->nullable();      // e.g. "month"
            $table->integer('plan_duration')->nullable(); // 1, 12, etc.
            $table->decimal('plan_amount', 12, 2)->default(0);
            $table->decimal('offer_price', 12, 2)->nullable();
            $table->string('currency', 8)->default('INR');
            $table->boolean('free')->default(false);
            $table->boolean('lifetime')->default(false);
            $table->boolean('status')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_custom_quote')->default(false);
            $table->integer('sort_order')->default(0);
            $table->text('detail')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->text('subtitle')->nullable();

            $table->integer('device_limit')->default(1);
            $table->integer('monthly_messages_limit')->default(0);
            $table->integer('contacts_limit')->default(0);
            $table->integer('broadcast_limit')->default(0);
            $table->integer('template_limit')->default(0);
            $table->integer('groups_limit')->default(0);
            $table->integer('campaign_messages_limit')->default(0);
            $table->integer('automation_messages_limit')->default(0);
            $table->integer('broadcast_size_limit')->default(0);
            $table->integer('total_campaigns_limit')->default(0);
            $table->integer('active_campaign_limit')->default(0);
            $table->integer('user_seat_limit')->default(0);
            $table->integer('tags_limit')->default(0);
            $table->integer('flow_limit')->default(0);
            $table->integer('flow_steps_limit')->default(0);
            $table->integer('autoreply_limit')->default(0);
            $table->integer('chatbot_limit')->default(0);
            $table->integer('scheduled_campaign_limit')->default(0);
            $table->integer('daily_media_size_allowance')->default(0);

            $table->boolean('autoreply')->default(false);
            $table->boolean('bulkmessage')->default(false);
            $table->boolean('schedulemessage')->default(false);
            $table->boolean('ads')->default(false);
            $table->boolean('campaign')->default(false);
            $table->boolean('autoflow')->default(false);
            $table->boolean('broadcast')->default(false);
            $table->boolean('chatgpt_suggestion')->default(false);
            $table->boolean('template')->default(false);
            $table->boolean('access_carousel_templates')->default(false);
            $table->boolean('role_based_permissions')->default(false);
            $table->boolean('access_drip_campaigns')->default(false);
            $table->boolean('access_ctwa')->default(false);
            $table->boolean('access_analytics')->default(false);
            $table->boolean('remove_branding')->default(false);

            $table->string('multipledevice')->nullable();
            $table->string('file_type_restrictions')->nullable();

            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
        Schema::dropIfExists('package_features');
    }
};

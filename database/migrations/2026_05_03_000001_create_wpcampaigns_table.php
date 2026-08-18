<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wpcampaigns — ported verbatim from the old project's WpCampaign model
 * (D:\wadesk_2806\New folder\app\Models\WpCampaign.php).
 *
 * Encrypted columns (campaign_name, custom_message, custom_header,
 * custom_footer, custom_buttons, custom_quick_replies) widened to text/longText
 * since ciphertext blows past the 255-char string limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wpcampaigns', function (Blueprint $table) {
            $table->id();
            $table->text('campaign_name');                          // encrypted
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('campaign_type')->default('text');       // text|template|button|flow|media
            $table->string('status')->default('draft');             // draft|scheduled|running|paused|cancelled|completed|failed
            $table->boolean('ab_testing')->default(false);
            $table->unsignedTinyInteger('ab_split')->default(50);
            $table->longText('custom_message')->nullable();         // encrypted
            $table->text('custom_header')->nullable();              // encrypted
            $table->text('custom_footer')->nullable();              // encrypted
            $table->longText('custom_buttons')->nullable();         // encrypted:array
            $table->longText('custom_quick_replies')->nullable();   // encrypted:array
            $table->string('custom_image')->nullable();
            $table->string('custom_video')->nullable();
            $table->string('custom_document')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('template_id_a')->nullable();
            $table->unsignedBigInteger('template_id_b')->nullable();
            $table->unsignedBigInteger('flow_id')->nullable();
            $table->boolean('use_attributes')->default(false);
            $table->boolean('tracking_enabled')->default(true);
            $table->string('schedule_type')->default('now');        // now|scheduled|recurring
            $table->date('send_date')->nullable();
            $table->time('send_time')->nullable();
            $table->string('timezone')->nullable();
            $table->string('node_schedule_id')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('responded_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wpcampaigns');
    }
};

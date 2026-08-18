<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wp_campaign_contacts — pivot/log table mirroring the old project's
 * WpCampaignContact model. Encrypted columns (phone_number, recipient_name,
 * error_message) widened to text since ciphertext exceeds 255 chars.
 *
 * Foreign keys are deliberately not constrained at the DB layer so that
 * cascading deletes can be done in PHP and the table works even when
 * referenced rows are softly handled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_campaign_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('variant')->nullable();                  // A|B for A/B testing
            $table->string('status')->default('queued');            // queued|sent|delivered|read|failed|unsubscribed|pending
            $table->text('phone_number')->nullable();               // encrypted
            $table->text('recipient_name')->nullable();             // encrypted
            $table->string('whatsapp_message_id')->nullable();
            $table->string('tracking_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->boolean('clicked')->default(false);
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->boolean('unsubscribed')->default(false);
            $table->boolean('is_unsubscribed')->default(false);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->text('error_message')->nullable();              // encrypted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_campaign_contacts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact-level tags. Tags already existed as a conversation concept
 * (conversation_tag); this adds the same tags to CONTACTS so an operator can
 * label a contact from /contacts — which is what fires the flow `tag_added`
 * audience trigger (FlowEnrollmentService::onTagAdded).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('contact_tag')) return;
        Schema::create('contact_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();
            $table->unique(['contact_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tag');
    }
};

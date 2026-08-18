<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp group directory (Jessica customization, P4 — Unofficial API only).
 *
 * The bot number is a member of each customer's WhatsApp group. We mirror the
 * groups + their participants here so that, when a private-chat order is
 * confirmed, we can FIND the customer's group by their phone and post the order
 * there with an @mention. WABA/Cloud API cannot do this — Baileys only.
 *
 *  - wa_groups        : one row per group the bot is in (per device/workspace)
 *  - wa_group_members : participants, keyed for a fast phone → group lookup
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('wa_groups')) {
            Schema::create('wa_groups', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->index();
                $t->string('device_phone', 24)->nullable()->index(); // bot number in the group (digits)
                $t->string('group_jid', 64);
                $t->string('subject', 191)->nullable();              // group name
                $t->unsignedInteger('size')->default(0);
                $t->string('group_code', 48)->nullable()->index();   // optional code baked into a wa.me link
                $t->json('meta_json')->nullable();
                $t->timestamp('synced_at')->nullable();
                $t->timestamps();
                $t->unique(['workspace_id', 'group_jid']);
            });
        }

        if (!Schema::hasTable('wa_group_members')) {
            Schema::create('wa_group_members', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->index();
                $t->string('group_jid', 64)->index();
                $t->string('phone', 24);                             // participant, digits only
                $t->boolean('is_admin')->default(false);
                $t->timestamp('synced_at')->nullable();
                $t->timestamps();
                $t->unique(['group_jid', 'phone']);
                $t->index(['workspace_id', 'phone']);                // reverse lookup: who is this customer
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_group_members');
        Schema::dropIfExists('wa_groups');
    }
};

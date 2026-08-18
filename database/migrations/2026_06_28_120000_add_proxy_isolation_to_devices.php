<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-number proxy / IP isolation (Unofficial-API / Baileys).
 *
 * Each Baileys number can egress through its OWN proxy IP so accounts on one
 * server don't share a single IP (lowers the "association ban" risk when many
 * numbers send bulk from the same box). Credentials are encrypted at rest
 * (the Device model casts proxy_username/password as `encrypted`). Default OFF
 * → every existing install behaves exactly as before (direct server IP).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('devices')) return;
        Schema::table('devices', function (Blueprint $t) {
            if (!Schema::hasColumn('devices', 'proxy_enabled'))    $t->boolean('proxy_enabled')->default(false)->after('status');
            if (!Schema::hasColumn('devices', 'proxy_type'))       $t->string('proxy_type', 8)->nullable()->after('proxy_enabled');   // 'http' | 'socks5'
            if (!Schema::hasColumn('devices', 'proxy_host'))       $t->string('proxy_host')->nullable()->after('proxy_type');
            if (!Schema::hasColumn('devices', 'proxy_port'))       $t->unsignedSmallInteger('proxy_port')->nullable()->after('proxy_host');
            if (!Schema::hasColumn('devices', 'proxy_username'))    $t->text('proxy_username')->nullable()->after('proxy_port');        // encrypted
            if (!Schema::hasColumn('devices', 'proxy_password'))    $t->text('proxy_password')->nullable()->after('proxy_username');    // encrypted
            if (!Schema::hasColumn('devices', 'proxy_status'))      $t->string('proxy_status', 16)->nullable()->after('proxy_password'); // 'ok' | 'unreachable'
            if (!Schema::hasColumn('devices', 'proxy_egress_ip'))   $t->string('proxy_egress_ip', 64)->nullable()->after('proxy_status');
            if (!Schema::hasColumn('devices', 'proxy_checked_at'))  $t->timestamp('proxy_checked_at')->nullable()->after('proxy_egress_ip');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('devices')) return;
        Schema::table('devices', function (Blueprint $t) {
            foreach (['proxy_enabled','proxy_type','proxy_host','proxy_port','proxy_username','proxy_password','proxy_status','proxy_egress_ip','proxy_checked_at'] as $c) {
                if (Schema::hasColumn('devices', $c)) $t->dropColumn($c);
            }
        });
    }
};

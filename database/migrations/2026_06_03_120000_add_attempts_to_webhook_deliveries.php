<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_deliveries', 'attempts')) {
                // How many HTTP attempts this delivery took (1 = first-try
                // success/failure; 2-3 = it was retried inline). Drives the
                // "retries" figure in the webhook health view.
                $table->unsignedTinyInteger('attempts')->default(1)->after('is_retry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('webhook_deliveries', 'attempts')) {
                $table->dropColumn('attempts');
            }
        });
    }
};

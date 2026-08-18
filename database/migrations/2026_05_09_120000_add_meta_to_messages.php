<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $t) {
            // Provider-specific extras the dispatcher needs at send time:
            // buttons (array of {type,text,value,url}), footer text, header
            // text, quick replies. Stored as JSON so Node can read whatever
            // shape the campaign / chat composer chose to push.
            $t->text('meta')->nullable()->after('reaction');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $t) {
            $t->dropColumn('meta');
        });
    }
};

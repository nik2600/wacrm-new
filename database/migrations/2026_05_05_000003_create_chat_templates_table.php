<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat templates table.
 *
 * Old project named this `templates_wadesk` and stored the category as
 * an integer FK into a categories table (1=Marketing, 2=Authentication,
 * 3=Utility — verified by reading
 * D:\wadesk_2806\New folder\app\Http\Controllers\MessageController.php).
 *
 * Here we use a string category column so the DB is self-describing
 * and we don't need a 3-row lookup table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('title');
            $table->string('category', 32)->index();          // marketing | utility | authentication
            $table->string('tone', 32)->nullable();
            $table->text('body');

            $table->string('media_path')->nullable();
            $table->string('media_type', 16)->nullable();

            // approved | pending | rejected | public
            $table->string('status', 16)->default('approved')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_templates');
    }
};

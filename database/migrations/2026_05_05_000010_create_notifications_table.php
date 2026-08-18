<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('category', 32)->default('system')->index();   // system / campaign / billing / chat / mention / contact / template / device / webhook / broadcast
            $table->string('severity', 16)->default('info');               // info / success / warning / danger
            $table->string('icon', 64)->nullable();                        // icon palette key
            $table->text('notification_title');                            // encrypted
            $table->text('notification_msg');                              // encrypted
            $table->string('source_type', 96)->nullable();                 // e.g. App\Models\Device
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('verb', 32)->nullable();                        // created / updated / deleted / failed / triggered
            $table->string('action_url')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('status')->default(true);                      // 1 = unread, 0 = read (legacy column name)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wallet + referrals + system settings — three new tables and three
 * new columns on users that together power the affiliate-credit-as-
 * messages flow.
 *
 * Why one migration, not three:
 *  - they're inert without each other (a referral row points at a
 *    transaction; transactions reference users; users reference each
 *    other for the referral graph). Splitting forces an awkward
 *    rollback order. One file = one cohesive feature add.
 *
 * Why `wallet_credits` is on `users` directly (instead of in a side
 * table that joins to wallet_transactions):
 *  - read path is hot. Every send checks the balance. A column read is
 *    one disk page; a SUM(amount) over the ledger grows linearly with
 *    history.
 *  - the ledger remains the source of truth — users.wallet_credits is
 *    just the materialised running total, kept in sync inside the same
 *    DB transaction that writes a wallet_transactions row. They cannot
 *    diverge.
 */
return new class extends Migration {
    public function up(): void
    {
        // ---- users : referral graph + balance -----------------------
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->nullable()->unique()->after('site_name');
            $table->unsignedBigInteger('referred_by_user_id')->nullable()->index()->after('referral_code');
            $table->unsignedBigInteger('wallet_credits')->default(0)->after('referred_by_user_id');
            // Currency wallet stored in paise/cents (integer) to dodge
            // floating-point grief. /100 at display time.
            $table->unsignedBigInteger('wallet_currency_minor')->default(0)->after('wallet_credits');
            $table->string('wallet_currency_code', 3)->default('INR')->after('wallet_currency_minor');

            $table->foreign('referred_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // ---- system_settings : key/value, type-tagged ---------------
        // Plain enough that we don't need a separate model layer per
        // setting — a typed get/set on the SystemSetting model is
        // plenty. `payload` lets us store complex JSON values later
        // (price tiers, country whitelists) without another migration.
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96)->unique();
            $table->string('type', 16)->default('int');  // int|float|bool|string|json
            $table->text('value')->nullable();           // canonical text representation
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ---- wallet_transactions : the ledger -----------------------
        // One row per balance change. `balance_after` is a
        // denormalisation: it lets the wallet history page paint
        // running totals without re-summing the whole ledger per row.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('kind', 16);                  // 'credit' | 'currency'
            $table->string('type', 16);                  // 'earn' | 'spend' | 'refund' | 'admin_adjust' | 'topup'
            $table->bigInteger('amount');                // signed; +earn/refund/topup, -spend/adjust-down
            $table->bigInteger('balance_after');
            $table->string('source', 64)->nullable();    // 'referral.signup'|'message.sent'|'broadcast.send'|'admin'|'razorpay.topup'
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['kind', 'source']);
            $table->index(['subject_type', 'subject_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // ---- referrals : one row per signup-with-ref -----------------
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_user_id');
            $table->unsignedBigInteger('referred_user_id');
            $table->string('code_used', 16);
            $table->unsignedInteger('credits_awarded')->default(0);
            $table->unsignedBigInteger('award_transaction_id')->nullable();   // wallet_transactions.id of the credit row
            $table->timestamp('created_at')->useCurrent();

            $table->unique('referred_user_id'); // one ref attribution per referred user, ever
            $table->index('referrer_user_id');
            $table->index('code_used');
            $table->foreign('referrer_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('referred_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Seed defaults so the send-time check never trips on a
        // missing setting on day one.
        \DB::table('system_settings')->insert([
            ['key' => 'referral_signup_credits', 'type' => 'int', 'value' => '100', 'description' => 'Credits awarded to the referrer when their referee signs up.', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'credits_per_message',     'type' => 'int', 'value' => '1',   'description' => 'Credits charged per outbound message.', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'credits_per_currency_minor', 'type' => 'float', 'value' => '0.1', 'description' => 'How many credits each minor currency unit (paise) buys when topping up. 0.1 = 1 credit per ₹0.10, i.e. 10 credits per ₹1.', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('system_settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn(['referral_code', 'referred_by_user_id', 'wallet_credits', 'wallet_currency_minor', 'wallet_currency_code']);
        });
    }
};

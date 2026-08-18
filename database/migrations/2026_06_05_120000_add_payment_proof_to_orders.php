<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline / bank-transfer payment-proof flow: the customer uploads a
 * receipt + reference (UTR / txn id), and an admin reviews + approves
 * or rejects it. These columns capture both the submission and the
 * review on the existing orders row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('failure_reason');
            $table->string('payment_reference')->nullable()->after('payment_proof_path');
            $table->text('proof_note')->nullable()->after('payment_reference');
            $table->timestamp('proof_submitted_at')->nullable()->after('proof_note');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('proof_submitted_at');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_proof_path',
                'payment_reference',
                'proof_note',
                'proof_submitted_at',
                'reviewed_by',
                'reviewed_at',
                'review_note',
            ]);
        });
    }
};

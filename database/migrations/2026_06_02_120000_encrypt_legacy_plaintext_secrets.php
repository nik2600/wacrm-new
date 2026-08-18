<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Encrypt-at-rest backfill for secrets that just gained encryption.
 *
 * Two stores, two encryption schemes:
 *  - OutboundWebhook.secret now uses Laravel's `encrypted` cast
 *    (Crypt::encryptString / decryptString). Its cast THROWS on a plaintext
 *    read, so any legacy plaintext row must be encrypted now or the webhook
 *    dispatch would error.
 *  - SystemSetting encrypts via Crypt::encrypt / decrypt and its get() already
 *    tolerates plaintext — but we encrypt existing rows for the newly-listed
 *    keys (shopify/hubspot/google secrets) so they're not stored in the clear.
 *
 * Idempotent: only rows that fail to decrypt (i.e. still plaintext) are touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) OutboundWebhook.secret — `encrypted` cast (string scheme).
        if (Schema::hasTable('outbound_webhooks')) {
            foreach (DB::table('outbound_webhooks')->whereNotNull('secret')->where('secret', '!=', '')->get(['id', 'secret']) as $row) {
                try {
                    Crypt::decryptString($row->secret); // already ciphertext → leave it
                } catch (DecryptException $e) {
                    DB::table('outbound_webhooks')->where('id', $row->id)
                        ->update(['secret' => Crypt::encryptString($row->secret)]);
                }
            }
        }

        // 2) SystemSetting rows for the encrypted keys — Crypt::encrypt scheme.
        if (Schema::hasTable('system_settings')) {
            $keys = \App\Models\SystemSetting::ENCRYPTED_KEYS;
            foreach (DB::table('system_settings')->whereIn('key', $keys)->whereNotNull('value')->where('value', '!=', '')->get(['id', 'value']) as $row) {
                try {
                    Crypt::decrypt($row->value); // already ciphertext → leave it
                } catch (DecryptException $e) {
                    DB::table('system_settings')->where('id', $row->id)
                        ->update(['value' => Crypt::encrypt($row->value)]);
                }
            }
        }
    }

    public function down(): void
    {
        // Irreversible by design — we never want to write secrets back as plaintext.
    }
};

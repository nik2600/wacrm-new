<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * `contacts.mobile` is encrypted (non-deterministic) → not queryable, so dedup
 * meant decrypting every row in memory. Add a deterministic `mobile_hash`
 * (sha256 of the canonicalised number) so Contact::rememberPhone() can dedup +
 * look up by phone in O(1) when auto-capturing manually-typed send numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            if (! Schema::hasColumn('contacts', 'mobile_hash')) {
                $t->string('mobile_hash', 64)->nullable()->after('mobile');
                $t->index('mobile_hash');
            }
        });

        // Backfill existing rows — mobile is encrypted, so read through the
        // model (auto-decrypts) then write the hash with a raw update to skip
        // model observers / re-encryption.
        try {
            \App\Models\Contact::query()
                ->select(['id', 'country_code', 'mobile'])
                ->whereNull('mobile_hash')
                ->orderBy('id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $c) {
                        $hash = \App\Models\Contact::hashPhone($c->country_code, $c->mobile);
                        if ($hash) {
                            DB::table('contacts')->where('id', $c->id)->update(['mobile_hash' => $hash]);
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('[MIGRATION] contacts mobile_hash backfill skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            if (Schema::hasColumn('contacts', 'mobile_hash')) {
                $t->dropIndex(['mobile_hash']);
                $t->dropColumn('mobile_hash');
            }
        });
    }
};

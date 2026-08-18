<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;

/**
 * One-shot data-hygiene command that re-saves every row whose
 * encrypted-cast columns currently hold plaintext (a legacy artefact
 * from before the casts were added, or from a raw INSERT that bypassed
 * the cast).
 *
 * Symptom this fixes: `GET /api/app/chats` (or any controller path that
 * presents Conversation.title / Conversation.preview / Message.body /
 * Message.to_number / Message.from_number / Message.failure_reason)
 * throwing `Illuminate\Contracts\Encryption\DecryptException: The
 * payload is invalid.` because Eloquent's encrypted cast can't decrypt
 * the plaintext stored in those columns.
 *
 * Safe: every row is loaded with the raw-original payload, fed through
 * Encrypter::decrypt() inside a try/catch to detect plaintext (a real
 * ciphertext decrypts cleanly → skip; plaintext throws → re-save with
 * the value as the model attribute so Laravel's encrypter handles the
 * write). Dry-run by default; pass --apply to actually persist.
 */
class BackfillEncryptedPlaintext extends Command
{
    protected $signature = 'wasnap:backfill-encrypted
                            {--apply : actually write the re-encrypted values (default is dry-run)}
                            {--chunk=200 : how many rows per chunk}';

    protected $description = 'Re-encrypt legacy plaintext rows in Conversation + Message encrypted columns so DecryptException stops firing.';

    /**
     * Detect whether a raw DB value can be decrypted by the current
     * APP_KEY. Returns true when the value either decrypts cleanly OR
     * is empty/null (nothing to do). Returns false ONLY when the cast
     * would throw at read time — those are the rows we need to backfill.
     */
    private function isDecryptable(?string $raw): bool
    {
        if ($raw === null || $raw === '') return true;
        try {
            \Illuminate\Support\Facades\Crypt::decrypt($raw, false);
            return true;
        } catch (DecryptException $e) {
            return false;
        }
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunk = max(50, min(2000, (int) $this->option('chunk') ?: 200));

        $this->info($apply
            ? '[backfill] APPLY mode — re-saving plaintext rows.'
            : '[backfill] DRY-RUN — pass --apply to actually re-save.');

        // ─── Conversation: title + preview ─────────────────────────────
        $convCols = ['title', 'preview'];
        $convFixed = 0;
        $convScanned = 0;
        DB::table('conversations')->select(array_merge(['id'], $convCols))->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$convFixed, &$convScanned, $convCols, $apply) {
                foreach ($rows as $row) {
                    $convScanned++;
                    $bad = [];
                    foreach ($convCols as $c) {
                        if (! $this->isDecryptable($row->{$c} ?? null)) $bad[] = $c;
                    }
                    if (! $bad) continue;
                    $convFixed++;
                    if (! $apply) {
                        $this->line("  conversations#{$row->id}: would re-encrypt [" . implode(',', $bad) . ']');
                        continue;
                    }
                    $conv = Conversation::find($row->id);
                    if (! $conv) continue;
                    foreach ($bad as $c) {
                        // Read raw plaintext from the row and set via the
                        // model — the cast will encrypt it on save.
                        $conv->{$c} = $row->{$c};
                    }
                    $conv->saveQuietly();
                }
            });

        // ─── Message: body + to_number + from_number + failure_reason ─
        $msgCols = ['body', 'to_number', 'from_number', 'failure_reason'];
        $msgFixed = 0;
        $msgScanned = 0;
        DB::table('messages')->select(array_merge(['id'], $msgCols))->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$msgFixed, &$msgScanned, $msgCols, $apply) {
                foreach ($rows as $row) {
                    $msgScanned++;
                    $bad = [];
                    foreach ($msgCols as $c) {
                        if (! $this->isDecryptable($row->{$c} ?? null)) $bad[] = $c;
                    }
                    if (! $bad) continue;
                    $msgFixed++;
                    if (! $apply) {
                        $this->line("  messages#{$row->id}: would re-encrypt [" . implode(',', $bad) . ']');
                        continue;
                    }
                    $m = Message::find($row->id);
                    if (! $m) continue;
                    foreach ($bad as $c) {
                        $m->{$c} = $row->{$c};
                    }
                    $m->saveQuietly();
                }
            });

        $this->newLine();
        $this->info('[backfill] conversations: scanned=' . $convScanned . ' ' . ($apply ? 're-encrypted' : 'would re-encrypt') . '=' . $convFixed);
        $this->info('[backfill] messages:      scanned=' . $msgScanned . ' ' . ($apply ? 're-encrypted' : 'would re-encrypt') . '=' . $msgFixed);
        if (! $apply && ($convFixed + $msgFixed) > 0) {
            $this->warn('Run with --apply to actually persist the re-encrypted values.');
        }

        return self::SUCCESS;
    }
}

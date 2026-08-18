<?php

namespace App\Console\Commands;

use App\Models\KeywordReply;
use App\Models\Workspace;
use App\Services\KeywordTranslationManager;
use Illuminate\Console\Command;

/**
 * One-time backfill: translate every existing keyword + reply into the
 * owning workspace's configured target languages. Safe to re-run —
 * the Translator service caches results for 24h, so a second pass over
 * an unchanged row is nearly free.
 *
 * Usage:
 *   php artisan auto-reply:translate-existing
 *   php artisan auto-reply:translate-existing --workspace=12
 *   php artisan auto-reply:translate-existing --force        (re-translate
 *       rows that already have translations, e.g. after editing the
 *       workspace's target-language list)
 */
class BackfillKeywordTranslations extends Command
{
    protected $signature = 'auto-reply:translate-existing
        {--workspace= : Only this workspace ID}
        {--force : Re-translate rows that already have translations}';

    protected $description = 'Translate every existing auto-reply keyword + content into the workspace\'s target languages.';

    public function handle(): int
    {
        $q = KeywordReply::query()->with(['contents', 'workspace']);
        if ($this->option('workspace')) {
            $q->where('workspace_id', (int) $this->option('workspace'));
        }
        if (!$this->option('force')) {
            $q->whereNull('keyword_translations');
        }

        $total = (clone $q)->count();
        if ($total === 0) {
            $this->info('Nothing to backfill — every row is already translated. Use --force to retranslate.');
            return self::SUCCESS;
        }

        $this->info("Translating {$total} keyword(s)…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = 0;
        $failed = 0;
        $q->chunkById(50, function ($rows) use (&$ok, &$failed, $bar) {
            foreach ($rows as $row) {
                try {
                    KeywordTranslationManager::translateForKeyword($row);
                    $ok++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->getOutput()->writeln('');
                    $this->warn("  ↳ #{$row->id} ({$row->keyword}) failed: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. {$ok} translated, {$failed} failed.");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

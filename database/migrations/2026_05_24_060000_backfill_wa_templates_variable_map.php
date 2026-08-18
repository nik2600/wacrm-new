<?php

use App\Models\WaTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill `variable_map` for legacy WaTemplate rows whose body /
 * header use double-brace placeholders (`{{name}}`).
 *
 * Pre-fix: `TemplatesController::buildVariableMap()` used a regex
 * that only matched single-brace `{var}`, so every template created
 * with the operator-facing form (which renders `{{name}}` per Meta
 * convention) stored `variable_map = null`. Combined with my new
 * `varsForRecipient` consumer, that nulls cascaded into empty body
 * params on every broadcast → Meta rejects with #132000.
 *
 * After fixing the regex (2026_05_24_060000 commit) this one-shot
 * migration walks every row and rebuilds the variable_map from the
 * current body/header text. Existing approved templates therefore
 * keep working without operators re-editing them.
 *
 * Safe to re-run — we only overwrite when extraction yields a
 * non-empty map. Rows that legitimately have no placeholders are
 * left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Use cursor() so very large tables don't blow up memory.
        WaTemplate::query()->chunkById(200, function ($templates) {
            foreach ($templates as $tpl) {
                $existing = $tpl->variable_map;
                if (is_array($existing) && !empty($existing)) continue;

                $map = $this->extract((string) $tpl->header, (string) $tpl->template_body);
                if ($map === null) continue;

                $tpl->variable_map = $map;
                $tpl->saveQuietly(); // skip observers + timestamps
            }
        });
    }

    public function down(): void
    {
        // No-op — we cannot reliably reverse a backfill (we'd erase
        // legitimate user-saved variable_maps along with the ones we
        // just generated). If you need to roll back, edit individual
        // rows.
    }

    private function extract(string $header, string $body): ?array
    {
        $worker = function (string $text): array {
            if ($text === '') return [];
            $out  = [];
            $seen = [];
            $i    = 1;
            if (preg_match_all('/\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/', $text, $m)) {
                foreach ($m[1] as $varName) {
                    if (isset($seen[$varName])) continue;
                    $seen[$varName] = true;
                    $out[] = ['num' => $i++, 'key' => $varName];
                }
            }
            return $out;
        };

        $map = [];
        if ($h = $worker($header)) $map['header'] = $h;
        if ($b = $worker($body))   $map['body']   = $b;
        return $map ?: null;
    }
};

<?php

namespace Database\Seeders;

use App\Models\LegalPage;
use Illuminate\Database\Seeder;

/**
 * Seeds the legal_pages table from the original hardcoded Blade files
 * (resources/views/frontend/legal/*.blade.php) so the admin editor starts
 * pre-filled with the real, current copy instead of blank. Idempotent:
 * only inserts a row if that slug doesn't already exist, so re-running never
 * clobbers admin edits.
 */
class LegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;
        foreach (LegalPage::SLUGS as $slug => $fallbackTitle) {
            $sort++;
            $existing = LegalPage::where('slug', $slug)->first();

            // Already has real content (seeded or admin-edited) — never clobber it.
            if ($existing && ! empty($existing->sections)) {
                continue;
            }

            $data = $this->extractFromBlade($slug);

            $payload = [
                'slug'            => $slug,
                'title'           => $data['title'] ?: $fallbackTitle,
                'subtitle'        => $data['subtitle'],
                'updated_label'   => $data['updated'],
                'effective_label' => $data['effective'],
                'sections'        => $data['sections'],
                'is_published'    => true,
                'sort'            => $sort,
            ];

            // Fill in a blank self-healed row, or create it fresh.
            if ($existing) {
                $existing->update($payload);
            } else {
                LegalPage::create($payload);
            }
        }
    }

    /**
     * Pull the title/subtitle/dates + sections out of a legal Blade file by
     * evaluating its PHP expressions ($sections block + the component attrs).
     * brand_name() resolves to the live brand so stored copy reads naturally.
     */
    private function extractFromBlade(string $slug): array
    {
        $blank = ['title' => '', 'subtitle' => null, 'updated' => null, 'effective' => null, 'sections' => []];
        $path = resource_path("views/frontend/legal/{$slug}.blade.php");
        if (! is_file($path)) {
            return $blank;
        }
        $src = file_get_contents($path);

        // 1) The @php ... @endphp block defines $sections.
        $sectionsExpr = '[]';
        if (preg_match('/@php(.*?)@endphp/s', $src, $m)) {
            $sectionsExpr = trim($m[1]); // "$sections = [ ... ];"
        }

        // 2) Scalar attrs are simple __() expressions with no inner double-quote.
        $attr = function (string $name) use ($src): string {
            return preg_match('/:' . preg_quote($name, '/') . '="([^"]*)"/', $src, $mm)
                ? $mm[1]
                : 'null';
        };

        $php = $sectionsExpr . "\n"
            . '$__title = ' . $attr('title') . ";\n"
            . '$__subtitle = ' . $attr('subtitle') . ";\n"
            . '$__updated = ' . $attr('updatedAt') . ";\n"
            . '$__effective = ' . $attr('effective') . ";\n"
            . 'return ["title" => $__title, "subtitle" => $__subtitle, "updated" => $__updated, "effective" => $__effective, "sections" => array_values($sections ?? [])];';

        try {
            $out = eval($php);
        } catch (\Throwable $e) {
            return $blank;
        }

        // Normalise sections to {n,title,body}.
        $out['sections'] = collect($out['sections'] ?? [])->map(fn ($s, $i) => [
            'n'     => trim((string) ($s['n'] ?? sprintf('%02d', $i + 1))),
            'title' => (string) ($s['title'] ?? ''),
            'body'  => trim((string) ($s['body'] ?? '')),
        ])->values()->all();

        return $out;
    }
}

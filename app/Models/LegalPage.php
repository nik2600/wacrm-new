<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A public legal document (Terms, Privacy, etc.), fully editable from
 * /admin/legal-pages. The public route renders straight from these columns.
 */
class LegalPage extends Model
{
    protected $fillable = [
        'slug', 'title', 'subtitle', 'updated_label',
        'effective_label', 'sections', 'is_published', 'sort',
    ];

    protected $casts = [
        'sections' => 'array',
        'is_published' => 'boolean',
    ];

    /** The five pages the footer links to, in display order. */
    public const SLUGS = [
        'terms'           => 'Terms of Service',
        'privacy'         => 'Privacy Policy',
        'refund'          => 'Refund Policy',
        'cookies'         => 'Cookie Policy',
        'acceptable-use'  => 'Acceptable Use',
    ];

    /** Normalised sections (always an array of n/title/body, never null). */
    public function orderedSections(): array
    {
        return collect($this->sections ?? [])
            ->map(fn ($s, $i) => [
                'n'     => trim((string) ($s['n'] ?? sprintf('%02d', $i + 1))),
                'title' => (string) ($s['title'] ?? ''),
                'body'  => (string) ($s['body'] ?? ''),
            ])
            ->values()
            ->all();
    }
}

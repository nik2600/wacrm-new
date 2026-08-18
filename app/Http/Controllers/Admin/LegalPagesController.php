<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin CRUD for the public legal documents. Every field the public page
 * renders is editable here — title, subtitle, the two date labels, and the
 * full ordered sections list (number / heading / body HTML).
 */
class LegalPagesController extends Controller
{
    public function index()
    {
        // Ensure all five canonical pages exist as rows (self-heal if a fresh
        // install never ran the seeder, or a slug was deleted).
        foreach (LegalPage::SLUGS as $slug => $title) {
            LegalPage::firstOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'is_published' => true, 'sections' => []]
            );
        }

        $pages = LegalPage::orderBy('sort')->orderBy('id')->get();

        return view('admin.legal-pages.index', compact('pages'));
    }

    public function edit(string $slug)
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();

        return view('admin.legal-pages.edit', [
            'page'  => $page,
            'label' => LegalPage::SLUGS[$slug] ?? $page->title,
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'title'           => ['required', 'string', 'max:160'],
            'subtitle'        => ['nullable', 'string', 'max:1000'],
            'updated_label'   => ['nullable', 'string', 'max:120'],
            'effective_label' => ['nullable', 'string', 'max:120'],
            'is_published'    => ['nullable'],
            'sections'        => ['nullable', 'array'],
            'sections.*.n'    => ['nullable', 'string', 'max:12'],
            'sections.*.title'=> ['nullable', 'string', 'max:200'],
            'sections.*.body' => ['nullable', 'string'],
        ]);

        // Rebuild sections: drop fully-empty rows, auto-number blanks, keep order.
        $sections = collect($request->input('sections', []))
            ->map(fn ($s) => [
                'n'     => trim((string) ($s['n'] ?? '')),
                'title' => trim((string) ($s['title'] ?? '')),
                'body'  => (string) ($s['body'] ?? ''),
            ])
            ->reject(fn ($s) => $s['title'] === '' && trim(strip_tags($s['body'])) === '')
            ->values()
            ->map(function ($s, $i) {
                if ($s['n'] === '') {
                    $s['n'] = sprintf('%02d', $i + 1);
                }
                return $s;
            })
            ->all();

        $page->update([
            'title'           => $data['title'],
            'subtitle'        => $data['subtitle'] ?: null,
            'updated_label'   => $data['updated_label'] ?: null,
            'effective_label' => $data['effective_label'] ?: null,
            'is_published'    => (bool) $request->boolean('is_published'),
            'sections'        => $sections,
        ]);

        Audit::log('legal_page.updated', [
            'layer'    => 'platform',
            'resource' => $page,
            'meta'     => ['slug' => $page->slug, 'sections' => count($sections)],
        ]);

        return back()->with('success', $page->title . ' ' . __('saved.'));
    }

    public function toggle(string $slug)
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();
        $page->update(['is_published' => ! $page->is_published]);

        Audit::log('legal_page.toggled', [
            'layer'    => 'platform',
            'resource' => $page,
            'meta'     => ['slug' => $page->slug, 'published' => $page->is_published],
        ]);

        return back()->with('success', $page->title . ' ' .
            ($page->is_published ? __('published.') : __('hidden.')));
    }
}

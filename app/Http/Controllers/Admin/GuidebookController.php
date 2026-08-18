<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuidebookArticle;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * /admin/guidebook — full CRUD over the help articles surfaced on the
 * user-side /guidebook page. Categories are free-text (default "general").
 *
 * Each article has:
 *   - title + slug (auto-generated from title if blank)
 *   - category (groups articles in the user sidebar)
 *   - excerpt (1-line preview in the user list)
 *   - body (Markdown — rendered server-side on the user view)
 *   - is_published (admin can save drafts)
 *   - sort_order (manual ordering within a category)
 *
 * Public-facing counters (views, helpful, not-helpful) are read-only
 * here; the user-side view + voting endpoint maintains them.
 */
class GuidebookController extends Controller
{
    public function index(Request $request): View
    {
        $q        = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');

        $base = GuidebookArticle::query()->orderBy('category')->orderBy('sort_order')->orderBy('title');
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                  ->orWhere('slug',  'like', "%{$q}%")
                  ->orWhere('body',  'like', "%{$q}%");
            });
        }
        if ($category !== '') $base->where('category', $category);
        $articles = $base->paginate(12)->withQueryString();

        $kpi = [
            'total'      => GuidebookArticle::count(),
            'published'  => GuidebookArticle::where('is_published', true)->count(),
            'drafts'     => GuidebookArticle::where('is_published', false)->count(),
            'views_30d'  => GuidebookArticle::sum('views_count'),
            'helpful'    => GuidebookArticle::sum('helpful_count'),
            'not_helpful'=> GuidebookArticle::sum('not_helpful_count'),
        ];
        $categories = GuidebookArticle::query()
            ->select('category')
            ->groupBy('category')
            ->pluck('category')
            ->all();

        return view('admin.guidebook.index', compact('articles', 'kpi', 'categories', 'q', 'category'));
    }

    public function create(): View
    {
        $article = null;
        $categories = GuidebookArticle::query()->select('category')->distinct()->pluck('category')->all();
        return view('admin.guidebook.edit', compact('article', 'categories'));
    }

    public function edit(int $id): View
    {
        $article = GuidebookArticle::findOrFail($id);
        $categories = GuidebookArticle::query()->select('category')->distinct()->pluck('category')->all();
        return view('admin.guidebook.edit', compact('article', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['title']);
        $data['published_at'] = $data['is_published'] ? now() : null;
        $article = GuidebookArticle::create($data);
        Audit::log('guidebook.created', ['subject_type' => 'guidebook_article', 'subject_id' => $article->id, 'meta' => ['title' => $article->title]]);
        return redirect()->route('admin.guidebook.index')->with('success', "Article \"{$article->title}\" created.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $article = GuidebookArticle::findOrFail($id);
        $data = $this->validated($request, $id);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['title'], $id);
        // Stamp published_at on first publish, leave it on subsequent edits.
        if ($data['is_published'] && ! $article->published_at) $data['published_at'] = now();
        if (! $data['is_published']) $data['published_at'] = null;
        $article->update($data);
        Audit::log('guidebook.updated', ['subject_type' => 'guidebook_article', 'subject_id' => $article->id]);
        return redirect()->route('admin.guidebook.index')->with('success', "Article \"{$article->title}\" updated.");
    }

    public function togglePublish(int $id): RedirectResponse
    {
        $a = GuidebookArticle::findOrFail($id);
        $a->is_published = ! $a->is_published;
        if ($a->is_published && ! $a->published_at) $a->published_at = now();
        $a->save();
        return back()->with('success', $a->title . ' is now ' . ($a->is_published ? 'published' : 'a draft') . '.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $a = GuidebookArticle::findOrFail($id);
        $title = $a->title;
        $a->delete();
        Audit::log('guidebook.deleted', ['subject_type' => 'guidebook_article', 'subject_id' => $id, 'meta' => ['title' => $title]]);
        return back()->with('success', "Article \"{$title}\" removed.");
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'title'       => 'required|string|max:200',
            'slug'        => 'nullable|string|max:160|regex:/^[a-z0-9-]*$/',
            'category'    => 'required|string|max:80',
            'excerpt'     => 'nullable|string|max:500',
            'body'        => 'nullable|string|max:200000',
            'is_published'=> 'sometimes|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]) + ['is_published' => (bool) $request->boolean('is_published')];
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        if ($slug === '') $slug = 'article-' . substr(md5(uniqid()), 0, 6);
        $candidate = $slug;
        $i = 1;
        while (GuidebookArticle::where('slug', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $i++;
            $candidate = "{$slug}-{$i}";
        }
        return $candidate;
    }
}

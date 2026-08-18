<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * /admin/blog — author marketing blog posts that render on the public
 * frontend (/blog + /blog/{slug}) with full per-post SEO + sitemap inclusion.
 */
class BlogController extends Controller
{
    public function index(Request $request)
    {
        $q = BlogPost::query()->with('category')->latest();

        if ($s = trim((string) $request->get('q', ''))) {
            $esc = addcslashes($s, '%_\\');
            $q->where(fn ($w) => $w->where('title', 'like', "%{$esc}%")->orWhere('slug', 'like', "%{$esc}%"));
        }
        if (in_array($request->get('status'), ['draft', 'published'], true)) {
            $q->where('status', $request->get('status'));
        }

        $posts = $q->paginate(15)->withQueryString();

        $stats = [
            'total'     => BlogPost::count(),
            'published' => BlogPost::where('status', 'published')->count(),
            'draft'     => BlogPost::where('status', 'draft')->count(),
            'views'     => (int) BlogPost::sum('views'),
        ];

        return view('admin.blog.index', compact('posts', 'stats'));
    }

    public function create()
    {
        return view('admin.blog.create', [
            'categories' => BlogCategory::orderBy('name')->get(),
        ]);
    }

    public function edit(int $id)
    {
        return view('admin.blog.create', [
            'post'       => BlogPost::findOrFail($id),
            'categories' => BlogCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, int $id)
    {
        return $this->persist($request, BlogPost::findOrFail($id));
    }

    public function destroy(int $id)
    {
        $post = BlogPost::findOrFail($id);
        foreach ([$post->featured_image, $post->og_image] as $img) {
            if ($img && media_storage()->exists($img)) {
                media_storage()->delete($img);
            }
        }
        $post->delete();
        return redirect()->route('admin.blog.index')->with('success', __('Post deleted.'));
    }

    public function toggle(int $id)
    {
        $post = BlogPost::findOrFail($id);
        if ($post->status === 'published') {
            $post->update(['status' => 'draft']);
        } else {
            $post->update(['status' => 'published', 'published_at' => $post->published_at ?: now()]);
        }
        return back()->with('success', $post->status === 'published' ? __('Published.') : __('Moved to draft.'));
    }

    private function persist(Request $request, ?BlogPost $post)
    {
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:200'],
            'slug'             => ['nullable', 'string', 'max:200'],
            'excerpt'          => ['nullable', 'string', 'max:500'],
            'body'             => ['nullable', 'string'],
            'category_id'      => ['nullable', 'integer', 'exists:blog_categories,id'],
            'new_category'     => ['nullable', 'string', 'max:120'],
            'tags'             => ['nullable', 'string', 'max:500'],
            'author_name'      => ['nullable', 'string', 'max:120'],
            'status'           => ['required', 'in:draft,published'],
            'published_at'     => ['nullable', 'date'],
            'is_featured'      => ['nullable', 'boolean'],
            'featured_image'   => ['nullable', 'image', 'max:8192'],
            'og_image'         => ['nullable', 'image', 'max:8192'],
            'meta_title'       => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'meta_keywords'    => ['nullable', 'string', 'max:255'],
            'canonical_url'    => ['nullable', 'url', 'max:255'],
            'noindex'          => ['nullable', 'boolean'],
        ]);

        // Resolve / create category.
        $categoryId = $data['category_id'] ?? null;
        if (!empty($data['new_category'])) {
            $cat = BlogCategory::create([
                'name' => $data['new_category'],
                'slug' => BlogCategory::uniqueSlug($data['new_category']),
            ]);
            $categoryId = $cat->id;
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = $slug !== '' ? Str::slug($slug) : ($post->slug ?? '');
        if ($slug === '' || (!$post)) {
            $slug = BlogPost::uniqueSlug($slug !== '' ? $slug : $data['title'], $post?->id);
        }

        $payload = [
            'title'            => $data['title'],
            'slug'             => $slug,
            'excerpt'          => $data['excerpt'] ?? null,
            'body'             => $data['body'] ?? null,
            'category_id'      => $categoryId,
            'tags'             => $this->parseTags($data['tags'] ?? null),
            'author_name'      => $data['author_name'] ?? null,
            'status'           => $data['status'],
            'published_at'     => $data['published_at'] ?? ($data['status'] === 'published' ? ($post->published_at ?? now()) : $post->published_at ?? null),
            'is_featured'      => (bool) ($data['is_featured'] ?? false),
            'meta_title'       => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords'    => $data['meta_keywords'] ?? null,
            'canonical_url'    => $data['canonical_url'] ?? null,
            'noindex'          => (bool) ($data['noindex'] ?? false),
        ];

        // Images → active media disk (cloud when enabled, else local).
        if ($request->hasFile('featured_image')) {
            if ($post?->featured_image && media_storage()->exists($post->featured_image)) {
                media_storage()->delete($post->featured_image);
            }
            $payload['featured_image'] = $request->file('featured_image')->store('blog', media_disk());
        }
        if ($request->hasFile('og_image')) {
            if ($post?->og_image && media_storage()->exists($post->og_image)) {
                media_storage()->delete($post->og_image);
            }
            $payload['og_image'] = $request->file('og_image')->store('blog', media_disk());
        }

        if ($post) {
            $post->update($payload);
        } else {
            $post = BlogPost::create($payload);
        }

        return redirect()->route('admin.blog.index')->with('success', __('Post saved.'));
    }

    private function parseTags(?string $raw): ?array
    {
        if (!$raw) {
            return null;
        }
        $tags = array_values(array_filter(array_map('trim', explode(',', $raw))));
        return $tags ?: null;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Public marketing surface.
 *
 * Renders the homepage / features / pricing pages that live at /, /features,
 * /pricing. Authed visitors hitting `/` get redirected to /home which fans
 * out to dashboard or team-inbox based on role — the marketing page is
 * strictly for guests.
 *
 * Auth-dashboard pricing (live $packages, yearly toggle, wallet credits)
 * lives at /account/plans and is served by PricingController, not this
 * controller.
 */
class FrontendController extends Controller
{
    public function home(Request $request)
    {
        // A verified storefront CUSTOM DOMAIN (or storefront subdomain) must serve
        // the SHOP at the root — not the platform's marketing homepage. Without
        // this, a client who CNAME'd shop.their-domain.com at us saw OUR landing
        // page instead of their store. Runs BEFORE the frontend kill-switch so
        // storefronts keep working even when the marketing site is turned off.
        $storefront = app(\App\Http\Controllers\StorefrontPublicController::class)->storefrontForHost($request);
        if ($storefront) {
            return app(\App\Http\Controllers\StorefrontPublicController::class)->index($request);
        }

        // Frontend kill-switch (admin → /admin/frontend toggle). When OFF, the
        // public HOMEPAGE redirects straight to login (guests) / the app
        // (authed) — but every OTHER page (privacy, terms, legal, pricing…)
        // keeps working. An admin previewing in the live editor (?fc_edit=1)
        // is exempt so the homepage stays editable.
        $editing = $request->boolean('fc_edit') && \Illuminate\Support\Facades\Auth::check();
        if (! (bool) \App\Models\SystemSetting::get('frontend_enabled', true) && ! $editing) {
            return \Illuminate\Support\Facades\Auth::check()
                ? redirect()->route('home')
                : redirect()->route('login');
        }

        return view('frontend.home');
    }

    public function features(Request $request)
    {
        return view('frontend.features');
    }

    public function pricing(Request $request)
    {
        return view('frontend.pricing');
    }

    public function about(Request $request)
    {
        return view('frontend.about');
    }

    public function contact(Request $request)
    {
        return view('frontend.contact');
    }

    /**
     * Public blog index — published posts, newest first, optional ?category=
     * and ?q= search. Paginated.
     */
    public function blogIndex(Request $request)
    {
        $query = \App\Models\BlogPost::published()->with('category')->latest('published_at');

        if ($slug = trim((string) $request->get('category', ''))) {
            $cat = \App\Models\BlogCategory::where('slug', $slug)->first();
            if ($cat) {
                $query->where('category_id', $cat->id);
            }
        }
        if ($s = trim((string) $request->get('q', ''))) {
            $esc = addcslashes($s, '%_\\');
            $query->where(fn ($w) => $w->where('title', 'like', "%{$esc}%")->orWhere('excerpt', 'like', "%{$esc}%"));
        }

        return view('frontend.blog.index', [
            'posts'      => $query->paginate(9)->withQueryString(),
            'categories' => \App\Models\BlogCategory::orderBy('name')->get(),
            'featured'   => \App\Models\BlogPost::published()->where('is_featured', true)->latest('published_at')->first(),
            'activeCategory' => $request->get('category', ''),
        ]);
    }

    /** Public blog post detail. Increments views; 404 on draft/missing. */
    public function blogShow(Request $request, string $slug)
    {
        $post = \App\Models\BlogPost::published()->with('category')->where('slug', $slug)->first();
        abort_unless($post, 404);

        // Cheap view counter (no model events / timestamps touched).
        \App\Models\BlogPost::whereKey($post->id)->update(['views' => $post->views + 1]);

        $related = \App\Models\BlogPost::published()
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->latest('published_at')->limit(3)->get();

        return view('frontend.blog.show', compact('post', 'related'));
    }

    /*
     * Legal pages — SaaS-CRM standard set. Content is fully admin-editable
     * via /admin/legal-pages (legal_pages table); the shared
     * <x-frontend.legal-page> component supplies the hero + TOC + chrome.
     */
    public function terms(Request $request)          { return $this->legal('terms'); }
    public function privacy(Request $request)        { return $this->legal('privacy'); }
    public function refund(Request $request)         { return $this->legal('refund'); }
    public function cookies(Request $request)        { return $this->legal('cookies'); }
    public function acceptableUse(Request $request)  { return $this->legal('acceptable-use'); }

    /**
     * Render a legal page from the DB. Hidden pages 404; if the row is
     * missing entirely (fresh install before the seeder ran) we fall back to
     * the original hardcoded blade so the page never breaks.
     */
    private function legal(string $slug)
    {
        $page = \App\Models\LegalPage::where('slug', $slug)->first();

        if ($page && ! $page->is_published) {
            abort(404);
        }
        if ($page) {
            return view('frontend.legal.dynamic', ['page' => $page]);
        }

        return view('frontend.legal.' . $slug);
    }
}

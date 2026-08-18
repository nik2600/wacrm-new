<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Response;

/**
 * Dynamic XML sitemap — public static pages + every published blog post +
 * blog categories. Served at /sitemap.xml (referenced by robots.txt) and
 * downloadable from /admin/blog.
 */
class SitemapController extends Controller
{
    /** GET /sitemap.xml */
    public function index(): Response
    {
        return response($this->build(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /** GET /admin/blog/sitemap/download */
    public function download(): Response
    {
        return response($this->build(), 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sitemap.xml"',
        ]);
    }

    /** GET /robots.txt — points crawlers at the sitemap. */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api',
            'Sitemap: ' . url('/sitemap.xml'),
        ];
        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [];

        // Static marketing pages.
        foreach ([
            ['/', '1.0', 'daily'],
            ['/features', '0.8', 'weekly'],
            ['/pricing', '0.8', 'weekly'],
            ['/about', '0.5', 'monthly'],
            ['/contact', '0.5', 'monthly'],
            ['/blog', '0.9', 'daily'],
        ] as [$path, $priority, $freq]) {
            $urls[] = ['loc' => url($path), 'priority' => $priority, 'changefreq' => $freq];
        }

        // Blog categories.
        foreach (BlogCategory::all() as $cat) {
            $urls[] = ['loc' => url('/blog?category=' . $cat->slug), 'priority' => '0.6', 'changefreq' => 'weekly'];
        }

        // Published posts (skip noindex).
        BlogPost::published()->where('noindex', false)->orderByDesc('published_at')->chunk(200, function ($posts) use (&$urls) {
            foreach ($posts as $p) {
                $urls[] = [
                    'loc'        => url('/blog/' . $p->slug),
                    'lastmod'    => optional($p->updated_at ?? $p->published_at)->toAtomString(),
                    'priority'   => $p->is_featured ? '0.8' : '0.7',
                    'changefreq' => 'weekly',
                ];
            }
        });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n    <loc>" . e($u['loc']) . "</loc>\n";
            if (!empty($u['lastmod'])) {
                $xml .= "    <lastmod>" . e($u['lastmod']) . "</lastmod>\n";
            }
            $xml .= "    <changefreq>" . e($u['changefreq']) . "</changefreq>\n";
            $xml .= "    <priority>" . e($u['priority']) . "</priority>\n  </url>\n";
        }
        $xml .= '</urlset>';

        return $xml;
    }
}

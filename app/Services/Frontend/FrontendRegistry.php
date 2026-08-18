<?php

namespace App\Services\Frontend;

/**
 * Describes the editable marketing pages and their sections for the
 * Frontend live editor. Used by:
 *   - the editor's "Sections" panel (list + show/hide toggles),
 *   - the composition Blades, which gate each section on visible().
 *
 * Field-level editing does NOT come from here — every editable element
 * carries a data-fc / data-fc-type attribute (added in P1) that the
 * editor reads straight from the DOM. This registry is only about which
 * sections exist on a page and whether each may be hidden.
 */
class FrontendRegistry
{
    /** Editable pages → label, route name, and default ordered section slugs. */
    public static function pages(): array
    {
        return [
            'home' => ['label' => 'Home', 'route' => 'frontend.home', 'sections' => [
                'hero-home', 'logo-strip', 'manifesto', 'feature-broadcasts', 'feature-flows',
                'feature-inbox', 'feature-templates', 'feature-connectivity', 'use-cases',
                'testimonials', 'pricing-strip', 'faq', 'cta-final',
            ]],
            'features' => ['label' => 'Features', 'route' => 'frontend.features', 'sections' => [
                'hero-features', 'logo-strip', 'feature-bento', 'pillars-three', 'pull-quote', 'faq', 'cta-final',
            ]],
            'pricing' => ['label' => 'Pricing', 'route' => 'frontend.pricing', 'sections' => [
                'pricing-hero', 'pricing-strip', 'faq', 'cta-final',
            ]],
            'about' => ['label' => 'About', 'route' => 'frontend.about', 'sections' => [
                'hero', 'origin-story', 'values', 'timeline', 'numbers', 'press', 'backers', 'pull-quote', 'cta-final',
            ]],
            'contact' => ['label' => 'Contact', 'route' => 'frontend.contact', 'sections' => [
                'hero', 'channels', 'form', 'faq', 'cta-final',
            ]],
        ];
    }

    /** Per-section metadata: display label + whether it can be hidden. */
    public static function sections(): array
    {
        return [
            // Heroes are structural — never hideable (a page needs a top).
            'hero-home'            => ['label' => 'Hero',                'removable' => false],
            'hero-features'        => ['label' => 'Hero',                'removable' => false],
            'pricing-hero'         => ['label' => 'Hero',                'removable' => false],
            'hero'                 => ['label' => 'Hero',                'removable' => false],

            // About page
            'origin-story'         => ['label' => 'Origin story',        'removable' => true],
            'values'               => ['label' => 'Values',             'removable' => true],
            'timeline'             => ['label' => 'Timeline',           'removable' => true],
            'numbers'              => ['label' => 'Numbers',            'removable' => true],
            'press'                => ['label' => 'Press',              'removable' => true],
            'backers'              => ['label' => 'Backers',            'removable' => true],

            // Contact page
            'channels'             => ['label' => 'Channels',           'removable' => true],
            'form'                 => ['label' => 'Contact form',       'removable' => true],

            'logo-strip'           => ['label' => 'Logo strip',          'removable' => true],
            'manifesto'            => ['label' => 'Manifesto',           'removable' => true],
            'feature-broadcasts'   => ['label' => 'Feature · Broadcasts','removable' => true],
            'feature-flows'        => ['label' => 'Feature · Flows',     'removable' => true],
            'feature-inbox'        => ['label' => 'Feature · Inbox',     'removable' => true],
            'feature-templates'    => ['label' => 'Feature · Templates', 'removable' => true],
            'feature-connectivity' => ['label' => 'Feature · Channels',  'removable' => true],
            'feature-bento'        => ['label' => 'Feature bento',       'removable' => true],
            'pillars-three'        => ['label' => 'Three pillars',       'removable' => true],
            'pull-quote'           => ['label' => 'Pull quote',          'removable' => true],
            'use-cases'            => ['label' => 'Use cases',           'removable' => true],
            'testimonials'         => ['label' => 'Testimonials',        'removable' => true],
            'pricing-strip'        => ['label' => 'Pricing cards',       'removable' => true],
            'faq'                  => ['label' => 'FAQ',                 'removable' => true],
            'cta-final'            => ['label' => 'Final CTA',           'removable' => true],
        ];
    }

    /** Should this section render? False only when an admin hid it + published. */
    public static function visible(string $page, string $slug): bool
    {
        $meta = static::sections()[$slug] ?? [];
        if (($meta['removable'] ?? true) === false) {
            return true; // heroes always show
        }
        $hidden = app(FrontendContentStore::class)->get("{$page}.__hidden", []);
        return !is_array($hidden) || !in_array($slug, $hidden, true);
    }
}

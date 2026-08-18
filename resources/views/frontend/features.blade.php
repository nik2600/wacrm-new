{{--
 Public features page. Hero is pinned; the rest are captured by slug and
 echoed in the admin-defined order (fc_section_order), skipping hidden
 ones. Per-page props (faq :items, cta-final :headline) are captured with
 their component, so reordering never loses them.
--}}
<x-layouts.frontend :title="__('Features')" nav-key="features" page="frontend-features">

    {{-- Hero: big editorial headline, 17 chip row, jump-to anchor card. --}}
    <x-frontend.hero-features />

    @php $sec = []; @endphp

    @php ob_start(); @endphp<x-frontend.logo-strip />@php $sec['logo-strip'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.feature-bento />@php $sec['feature-bento'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.pillars-three />@php $sec['pillars-three'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.pull-quote />@php $sec['pull-quote'] = ob_get_clean(); @endphp

    @php ob_start(); @endphp
    <x-frontend.faq :kicker="__('FAQ')" scope="features" :items="[
        [
            'q' => __('Are all features included on every plan?'),
            'a' => __(
                'Yes — even on Starter (free). Volume limits scale with your plan, but every feature is unlocked from day one. No \'Enterprise-only\' toggles.',
            ),
            'open' => true,
        ],
        [
            'q' => __('Can I use only some features and ignore the rest?'),
            'a' => __(
                'Absolutely. Many customers start with just broadcasts + inbox. The other features sit dormant until you need them — no bloat in the UI, no nag emails.',
            ),
        ],
        [
            'q' => __('How does AI Copilot work with my data?'),
            'a' => __(
                'Copilot runs on GPT-4o + Claude 3.5 with your prompts redacted of PII before reaching the model. Your messages are never used for training.',
            ),
        ],
        [
            'q' => __('Which features ship together vs. independently?'),
            'a' => __(
                'All ship together on a single platform release. We deploy weekly. Every feature shares the same data layer, design system, and API.',
            ),
        ],
        [
            'q' => __('Can I migrate from AiSensy, Wati, Interakt, or Gupshup?'),
            'a' => __(
                'One-click importers for all four. White-glove migration is free on Pro & Scale — we move your contacts, templates, flows, and history in 3 days with zero downtime.',
            ),
        ],
        [
            'q' => __('Where can I see what\'s shipping next?'),
            'a' => __(
                'Our public roadmap lives at /changelog — features are tagged \'shipping this quarter\', \'in beta\', \'planned\'. Customers can upvote any item.',
            ),
        ],
    ]" />
    @php $sec['faq'] = ob_get_clean(); @endphp

    @php ob_start(); @endphp
    <x-frontend.cta-final :headline="__('All eighteen.<br>One <span class=\'italic text-wa-green\'>workspace.</span>')" kicker="One bill · one login" />
    @php $sec['cta-final'] = ob_get_clean(); @endphp

    @php
        foreach (fc_section_order('features', array_keys($sec)) as $slug) {
            if (fc_section_visible('features', $slug)) {
                echo $sec[$slug];
            }
        }
    @endphp

</x-layouts.frontend>

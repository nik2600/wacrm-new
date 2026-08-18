{{--
 Public pricing page. Renders the editorial Starter/Pro/Scale strip
 with the component's default plan shape. Auth dashboard pricing
 (live $packages + yearly toggle + wallet) is at /account/plans.
--}}
<x-layouts.frontend :title="__('Pricing')" nav-key="pricing" page="frontend-pricing">

    {{-- top kicker --}}
    <section class="relative overflow-hidden bg-paper-0">
        <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>

        <div class="relative max-w-[1360px] mx-auto px-7 py-28">
            <div data-fc="pricing.hero.eyebrow" class="badge-num mb-6">{{ fc('pricing.hero.eyebrow', __('— Pricing')) }}</div>
            <h1 data-fc="pricing.hero.headline" data-fc-type="richtext" class="serif text-[88px] leading-[0.92] tracking-[-0.02em]">
                {!! fc('pricing.hero.headline', __('Simple, honest,<br>and <span class="italic text-wa-deep">flat.</span>')) !!}
            </h1>
            <p data-fc="pricing.hero.intro" class="text-[15.5px] text-ink-700 mt-6 max-w-2xl leading-relaxed">
                {{ fc('pricing.hero.intro', __('Pay for conversations, never per seat. 14-day free trial. No credit card. Cancel any time, keep your data.')) }}
            </p>
        </div>
    </section>

    {{-- The hero kicker above is pinned. The strip / FAQ / CTA below are
 captured by slug and echoed in the admin-defined order, hidden ones
 skipped. The pricing-strip keeps :show-header="false" (this page is
 already the pricing page). --}}
    @php $sec = []; @endphp

    @php ob_start(); @endphp<x-frontend.pricing-strip :show-header="false" />@php $sec['pricing-strip'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.faq :kicker="__('Pricing FAQ')" scope="pricing" />@php $sec['faq'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.cta-final />@php $sec['cta-final'] = ob_get_clean(); @endphp

    @php
        foreach (fc_section_order('pricing', array_keys($sec)) as $slug) {
            if (fc_section_visible('pricing', $slug)) {
                echo $sec[$slug];
            }
        }
    @endphp

</x-layouts.frontend>

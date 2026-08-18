{{--
 Public homepage — composed entirely of <x-frontend.*> components.

 The hero is pinned to the top. Every other section is captured to a
 string keyed by its slug, then echoed in the admin-defined order
 (fc_section_order) and skipped when hidden (fc_section_visible). With no
 admin edits this renders in the exact shipped order, visibly identical.
--}}
<x-layouts.frontend :title="__('Home')" nav-key="product" page="frontend-home">

    {{-- Hero: atmospheric headline + 4 ambient bubble accents. Two-device
 stage mounts into the hero's slot. The hero is structural and always
 renders first — it is not part of the reorderable set. --}}
    <x-frontend.hero-home>
        <x-frontend.two-device-stage />
    </x-frontend.hero-home>

    @php $sec = []; @endphp
    @php ob_start(); @endphp<x-frontend.logo-strip />@php $sec['logo-strip'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.manifesto />@php $sec['manifesto'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.feature-broadcasts />@php $sec['feature-broadcasts'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.feature-flows />@php $sec['feature-flows'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.feature-inbox />@php $sec['feature-inbox'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.feature-templates />@php $sec['feature-templates'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.feature-connectivity />@php $sec['feature-connectivity'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.use-cases />@php $sec['use-cases'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.testimonials />@php $sec['testimonials'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.pricing-strip />@php $sec['pricing-strip'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.faq />@php $sec['faq'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.faq-slider />@php $sec['faq-slider'] = ob_get_clean(); @endphp
    @php ob_start(); @endphp<x-frontend.cta-final />@php $sec['cta-final'] = ob_get_clean(); @endphp

    @php
        foreach (fc_section_order('home', array_keys($sec)) as $slug) {
            if (fc_section_visible('home', $slug)) {
                echo $sec[$slug];
            }
        }
    @endphp

</x-layouts.frontend>

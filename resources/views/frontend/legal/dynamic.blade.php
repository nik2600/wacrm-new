{{--
 Public legal page rendered from the admin-editable legal_pages table.
 The chrome (hero, TOC, section styling) lives in <x-frontend.legal-page>;
 every word here comes from /admin/legal-pages.
--}}
<x-frontend.legal-page
    :title="$page->title"
    :subtitle="$page->subtitle"
    :updatedAt="$page->updated_label"
    :effective="$page->effective_label"
    :sections="$page->orderedSections()" />

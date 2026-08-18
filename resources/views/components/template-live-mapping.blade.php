{{--
    Send-time template mapping panel — ONE component, four call sites.

    Lets an operator edit the selected template's variable values, header
    media, and button links for THIS SEND ONLY. Nothing here touches the
    template row, so the same template stays reusable everywhere else.

    Usage:
        <x-template-live-mapping :templates="$templates" target="#template-only" />

    Requires:
      - $templates : Collection<WaTemplate> — the same list the picker renders
      - target     : CSS selector of the template <select> to watch

    The picker's own <option>s must carry data-send-meta; this component
    injects it on boot so callers don't have to change their markup.

    Values post as `template_overrides` and are read back by
    App\Services\TemplateOverrideResolver.

    @see resources/js/charts/template-live-mapping.js
    @see App\Models\WaTemplate::sendMeta()
    @see App\Services\SendAttributes
--}}

@props([
    'templates' => collect(),
    'target' => '[data-tlm-template]',
    'value' => null,
    // Name of the hidden input this panel posts. Defaults to the single-template
    // field; an A/B campaign renders a SECOND panel with field="template_overrides_b"
    // so each variant's template gets its own mapping. Without this the two
    // panels would both post `template_overrides` and the last one in the DOM
    // would silently overwrite the other.
    'field' => 'template_overrides',
])

@php
    // Attribute catalog — the click-to-insert list. Resolved once per page.
    $tlmWorkspaceId = (int) (auth()->user()->current_workspace_id ?? 0);
    $tlmAttributes = $tlmWorkspaceId
        ? app(\App\Services\SendAttributes::class)->catalog($tlmWorkspaceId)
        : [];

    // Per-template shape, keyed by id. The JS reads it off the picked option.
    $tlmMeta = [];
    foreach ($templates as $tlmT) {
        try {
            $tlmMeta[(int) $tlmT->id] = $tlmT->sendMeta();
        } catch (\Throwable $e) {
            // A malformed template row must not blank the whole send page —
            // it just doesn't get an editable panel.
            report($e);
        }
    }

    // Existing overrides on an edit screen.
    $tlmValue = $value;
    if (is_array($tlmValue)) {
        $tlmValue = json_encode($tlmValue);
    }
@endphp

<div data-tlm-root data-tlm-template="{{ $target }}" class="mt-3">
    <input type="hidden" name="{{ $field }}" data-tlm-input value="{{ $tlmValue ?? old($field) }}">
    <div data-tlm-panel class="hidden"></div>
</div>

@once
    @push('scripts')
        <script>
            // Catalog + per-template shape handed to the panel. Written as
            // JSON (not interpolated into JS expressions) so operator-authored
            // labels and sample values can't break out into code.
            window.__tlmAttributes = @json($tlmAttributes);
            window.__tlmMeta = Object.assign(window.__tlmMeta || {}, @json($tlmMeta));

            // Stamp each <option> with its template's shape. Done here rather
            // than in the picker blades so every existing send screen keeps
            // its markup unchanged.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-tlm-root]').forEach(function (root) {
                    var sel = document.querySelector(root.dataset.tlmTemplate || '[data-tlm-template]');
                    if (!sel) return;
                    sel.setAttribute('data-tlm-template', '');
                    Array.prototype.forEach.call(sel.options, function (opt) {
                        var m = window.__tlmMeta[opt.value];
                        if (m) opt.dataset.sendMeta = JSON.stringify(m);
                    });
                });
            });
        </script>
    @endpush
@endonce

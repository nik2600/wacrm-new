{{--
    Injects the active locale's translation map into window.__i18n so the
    client-side chat / team-inbox renderers can localise their UI strings via
    window.t(). Emitted ONLY for non-English locales (English needs no map —
    window.t falls back to the English key). Keyed by the English source string,
    matching how lang/<locale>.json is keyed, so t('Reply') → 'Responder'.

    @include this in any blade whose JS renders user-facing text.
--}}
@php
    $__jsI18n = [];
    if (app()->getLocale() !== 'en') {
        try {
            $__jsI18n = app('translator')->getLoader()->load(app()->getLocale(), '*', '*');
        } catch (\Throwable $e) {
            $__jsI18n = [];
        }
    }
@endphp
@if (!empty($__jsI18n))
    @push('scripts')
        <script>
            window.__i18n = Object.assign(window.__i18n || {}, @json((object) $__jsI18n));
        </script>
    @endpush
@endif

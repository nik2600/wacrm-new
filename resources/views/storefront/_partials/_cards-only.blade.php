{{-- AJAX partial for the Show-more button. Echoes JUST the card
 markup for the requested page, plus a tiny JSON pill in a
 comment for the JS to read (next-page existence + page #). --}}
@foreach ($products as $p)
    @include('storefront._partials.card', ['p' => $p])
@endforeach
<script type="application/json" data-page-meta>{!! json_encode(['hasMore' => $hasMore, 'page' => $page]) !!}</script>

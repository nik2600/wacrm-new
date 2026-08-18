@php $theme = ['brand'=>$settings['brand_color']??'#E76F51','bg'=>'#FFF6EC','surface'=>'#FFFFFF','text'=>'#2A1810','muted'=>'#7A5C40','border'=>'#F4D7B8','accent'=>'#E76F51','cardRadius'=>'10px','heroSize'=>'42px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    .grid{grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
    .card .body{padding:10px}
    .card h3{font-size:15px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:600}
    .card .price{font-size:13px;font-weight:700;color:#E76F51}
    .card .add{background:{{ $theme['accent'] }};color:#fff;border:none;border-radius:6px;padding:6px 10px;font-size:11px}
    .card .add:hover{filter:brightness(1.05)}
@endsection
@section('content')
    @include('storefront._partials.index')
@endsection

@php $theme = ['brand'=>$settings['brand_color']??'#D4AF37','bg'=>'#0A0A0A','surface'=>'#141414','text'=>'#F0F0F0','muted'=>'#A0A0A0','border'=>'#2A2A2A','accent'=>'#D4AF37','cardRadius'=>'4px','heroSize'=>'52px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    header.sf-head{background:#0A0A0A;border-bottom:1px solid #2A2A2A}
    .hero h1{font-weight:300;letter-spacing:0.02em}
    .card{background:#141414;border:1px solid #2A2A2A}
    .card:hover{border-color:#D4AF37}
    .card .img{background:#0A0A0A}
    .card h3{font-weight:300}
    .cart-btn{background:transparent;border:1px solid {{ $theme['accent'] }};color:{{ $theme['accent'] }}}
    .cart-btn [data-cart-count]{background:{{ $theme['accent'] }};color:#000}
    .add{background:transparent;border:1px solid {{ $theme['accent'] }};color:{{ $theme['accent'] }}}
    .add:hover{background:{{ $theme['accent'] }};color:#000}
@endsection
@section('content')
    @include('storefront._partials.index')
@endsection

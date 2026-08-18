@php $theme = ['brand'=>$settings['brand_color']??'#5C7C4D','bg'=>'#F4F1E8','surface'=>'#FAF8F0','text'=>'#2D3A22','muted'=>'#7B8A6A','border'=>'#D5DCC4','accent'=>'#5C7C4D','cardRadius'=>'20px','heroSize'=>'44px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    .hero{position:relative}
    .hero::before{content:'';position:absolute;top:20px;left:50%;transform:translateX(-50%);width:60px;height:2px;background:{{ $theme['accent'] }};border-radius:9999px}
    .card{border-radius:20px}
    .card .img{aspect-ratio:4/5;background:linear-gradient(135deg,#E8E2D0,#D5DCC4)}
@endsection
@section('content')
    @include('storefront._partials.index')
@endsection

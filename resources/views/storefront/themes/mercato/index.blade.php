@php $theme = ['brand'=>$settings['brand_color']??'#C8553D','bg'=>'#FFF8E7','surface'=>'#FFFEF8','text'=>'#1F1408','muted'=>'#7C5B3A','border'=>'#F4D35E','accent'=>'#C8553D','cardRadius'=>'12px','heroSize'=>'46px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    header.sf-head{background:#C8553D;color:#FFF8E7;border-bottom:none}
    header.sf-head .sf-brand{color:#FFF8E7;font-family:'Fraunces',serif;font-style:italic}
    .cart-btn{background:#F4D35E;color:#1F1408}
    .hero{background:repeating-linear-gradient(90deg,#FFF8E7 0,#FFF8E7 24px,#FFFEF8 24px,#FFFEF8 48px);border-bottom:4px
    dashed #F4D35E;padding-bottom:60px}
    .hero h1{font-style:italic}
    .card{border:2px solid #F4D35E}
    .card .img{background:#FFF8E7}
    .add{background:#F4D35E;color:#1F1408;border:none}
    .add:hover{background:#C8553D;color:#fff}
@endsection
@section('content')
    @include('storefront._partials.index')
@endsection

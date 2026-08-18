@php $theme = ['brand'=>$settings['brand_color']??'#A6845A','bg'=>'#EDE4D3','surface'=>'#F5EFE0','text'=>'#3B2C1A','muted'=>'#8B7556','border'=>'#C9B894','accent'=>'#A6845A','cardRadius'=>'8px','heroSize'=>'42px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    body{background-image:radial-gradient(rgba(166,132,90,0.08) 1px, transparent 1px);background-size:8px 8px}
    header.sf-head{background:rgba(245,239,224,0.95);backdrop-filter:blur(6px)}
    .card{box-shadow:2px 2px 0 #C9B894}
    .card .img{background:#E5D8B7}
@endsection
@section('content')
    @include('storefront._partials.index')
@endsection

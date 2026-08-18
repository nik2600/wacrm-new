@php $theme = ['brand'=>$settings['brand_color']??'#A6845A','bg'=>'#EDE4D3','surface'=>'#F5EFE0','text'=>'#3B2C1A','muted'=>'#8B7556','border'=>'#C9B894','accent'=>'#A6845A','cardRadius'=>'8px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    body{background-image:radial-gradient(rgba(166,132,90,0.08) 1px, transparent 1px);background-size:8px 8px}
@endsection
@section('content')
    @include('storefront._partials.product')
@endsection

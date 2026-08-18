@php $theme = ['brand'=>$settings['brand_color']??'#D4AF37','bg'=>'#0A0A0A','surface'=>'#141414','text'=>'#F0F0F0','muted'=>'#A0A0A0','border'=>'#2A2A2A','accent'=>'#D4AF37','cardRadius'=>'4px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    header.sf-head{background:#0A0A0A;border-bottom:1px solid #2A2A2A}
    .order-btn{background:{{ $theme['accent'] }};color:#000}
@endsection
@section('content')
    @include('storefront._partials.product')
@endsection

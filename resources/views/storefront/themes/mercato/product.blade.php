@php $theme = ['brand'=>$settings['brand_color']??'#C8553D','bg'=>'#FFF8E7','surface'=>'#FFFEF8','text'=>'#1F1408','muted'=>'#7C5B3A','border'=>'#F4D35E','accent'=>'#C8553D','cardRadius'=>'12px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    header.sf-head{background:#C8553D;color:#FFF8E7;border-bottom:none}.sf-brand{color:#FFF8E7!important;font-style:italic}.cart-btn{background:#F4D35E;color:#1F1408}
@endsection
@section('content')
    @include('storefront._partials.product')
@endsection

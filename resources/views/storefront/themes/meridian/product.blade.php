@php $theme = ['brand'=>$settings['brand_color']??'#0B1F1C','bg'=>'#F5F2EA','surface'=>'#FFFFFF','text'=>'#0B1F1C','muted'=>'#6B807C','border'=>'#D9D2BD','accent'=>'#0B1F1C','heroSize'=>'52px','cardRadius'=>'2px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('content')
    @include('storefront._partials.product')
@endsection

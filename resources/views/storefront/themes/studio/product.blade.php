@php $theme = ['brand'=>$settings['brand_color']??'#2A9D8F','bg'=>'#F4F1ED','surface'=>'#FFFFFF','text'=>'#264653','muted'=>'#7B8E94','border'=>'#D8DCDD','accent'=>'#2A9D8F','cardRadius'=>'24px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('content')
    @include('storefront._partials.product')
@endsection

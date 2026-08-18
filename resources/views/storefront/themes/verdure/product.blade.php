@php $theme = ['brand'=>$settings['brand_color']??'#5C7C4D','bg'=>'#F4F1E8','surface'=>'#FAF8F0','text'=>'#2D3A22','muted'=>'#7B8A6A','border'=>'#D5DCC4','accent'=>'#5C7C4D','cardRadius'=>'20px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('content')
    @include('storefront._partials.product')
@endsection

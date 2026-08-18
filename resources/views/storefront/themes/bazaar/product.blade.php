@php $theme = ['brand'=>$settings['brand_color']??'#E76F51','bg'=>'#FFF6EC','surface'=>'#FFFFFF','text'=>'#2A1810','muted'=>'#7A5C40','border'=>'#F4D7B8','accent'=>'#E76F51','cardRadius'=>'10px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('content')
    @include('storefront._partials.product')
@endsection

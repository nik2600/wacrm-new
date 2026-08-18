@php $theme = ['brand'=>$settings['brand_color']??'#075E54','bg'=>'#FBFAF6','surface'=>'#FFFFFF','text'=>'#0B1F1C','muted'=>'#6B807C','border'=>'#E5DFD0','accent'=>$settings['brand_color']??'#075E54']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('content')
    @include('storefront._partials.product')
@endsection

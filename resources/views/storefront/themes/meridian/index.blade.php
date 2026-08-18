@php $theme = ['brand'=>$settings['brand_color']??'#0B1F1C','bg'=>'#F5F2EA','surface'=>'#FFFFFF','text'=>'#0B1F1C','muted'=>'#6B807C','border'=>'#D9D2BD','accent'=>'#0B1F1C','serif'=>"'Fraunces',serif",'heroSize'=>'58px','cardRadius'=>'2px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    .hero h1 em{font-style:italic;font-weight:400} .card{border:none;border-bottom:1px solid
    {{ $theme['border'] }};border-radius:0} .card .body{padding:14px 0} .grid{padding:24px 8%;gap:64px
    24px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
    @media (max-width:600px){.grid{grid-template-columns:1fr;padding:24px 16px}}
@endsection
@section('content')
    @include('storefront._partials.index')
@endsection

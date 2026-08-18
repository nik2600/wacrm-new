@php $theme = ['brand'=>$settings['brand_color']??'#2A9D8F','bg'=>'#F4F1ED','surface'=>'#FFFFFF','text'=>'#264653','muted'=>'#7B8E94','border'=>'#D8DCDD','accent'=>'#2A9D8F','cardRadius'=>'24px','heroSize'=>'56px']; @endphp
@extends('storefront._theme-base', ['theme' => $theme])
@section('extra-styles')
    .hero{padding:96px 24px 32px;text-align:left;max-width:900px;margin:0 auto}
    .hero h1{font-weight:500}
    .grid{grid-template-columns:repeat(auto-fill,minmax(320px,1fr));max-width:1100px;margin:0 auto;gap:32px}
    @media (max-width:600px){.grid{grid-template-columns:1fr}}
    .card{box-shadow:0 12px 32px rgba(38,70,83,0.06);border:none}
    .card:hover{box-shadow:0 20px 48px rgba(38,70,83,0.10)}
    .card .img{aspect-ratio:5/4}
    .add{background:#2A9D8F;color:#fff;border:none;border-radius:9999px;padding:9px 18px}
    .add:hover{background:#264653}
@endsection
@section('content')
    @include('storefront._partials.index')
@endsection

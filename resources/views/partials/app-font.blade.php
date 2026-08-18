{{-- Admin-selected UI font (general settings → font_family). Loads the chosen
     Google font and overrides Tailwind's --font-sans + the body font so the
     whole app picks it up. Emits nothing when the theme default is selected,
     so an untouched install keeps its original typography. --}}
@php $__appFont = function_exists('app_font') ? app_font() : ['stack' => '', 'url' => '']; @endphp
@if(!empty($__appFont['url']))
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $__appFont['url'] }}" rel="stylesheet">
    <style>:root{--font-sans:{{ $__appFont['stack'] }};}body{font-family:{{ $__appFont['stack'] }};}</style>
@endif

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fatal trace
    |--------------------------------------------------------------------------
    |
    | Arms App\Support\FatalTrace on instrumented actions. It costs one
    | DB::listen closure per armed request and writes nothing unless the
    | request actually dies on a PHP fatal, so it is cheap to leave on.
    |
    | Set FATAL_TRACE=false in .env once a diagnosis is finished.
    |
    */

    'fatal_trace' => env('FATAL_TRACE', true),

];

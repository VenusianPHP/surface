<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default CPU Engine
    |--------------------------------------------------------------------------
    |
    | Available options: 'dirty', 'spectra-full', 'byte-packed'
    |
    */
    'default' => env('CPU_ENGINE', 'dirty'),

    /*
    |--------------------------------------------------------------------------
    | Engine aliases
    |--------------------------------------------------------------------------
    |
    | The container alias each engine package binds its CPUEngineDriver under.
    | Rebind one here to point an engine name at a different package without
    | code. Missing package: the container's own not-found exception.
    |
    */
    'engines' => [

    ],
];
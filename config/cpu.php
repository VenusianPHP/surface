<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default CPU Engine
    |--------------------------------------------------------------------------
    |
    | Used when a sketch names no engine.
    | Available options: 'dirty', 'full', 'epaper', 'paged', 'nframes'
    |
    */
    'default' => env('CPU_ENGINE', 'dirty'),

    /*
    |--------------------------------------------------------------------------
    | Engine aliases
    |--------------------------------------------------------------------------
    |
    | The container alias each engine binds its CPUEngineDriver under. The five
    | in-house engines are bound by Surface's DrawingServiceProvider; rebind
    | one here to point an engine name at another package without code.
    |
    */
    'engines' => [
        'dirty' => ['alias' => 'cpu.dirty'],
        'full' => ['alias' => 'cpu.full'],
        'epaper' => ['alias' => 'cpu.epaper'],
        'paged' => ['alias' => 'cpu.paged'],
        'nframes' => ['alias' => 'cpu.nframes'],
    ],
];
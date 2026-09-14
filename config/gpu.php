<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default GPU Engine
    |--------------------------------------------------------------------------
    |
    | Used by Windowable::gpu() when the sketch names no engine.
    | Available options: 'metal', 'opengl', 'vulkan', 'sdl3'
    |
    */
    'default' => env('GPU_ENGINE', device_os_family() === 'mac' ? 'metal' : 'opengl'),

    /*
    |--------------------------------------------------------------------------
    | Engine aliases
    |--------------------------------------------------------------------------
    |
    | The container alias each engine package binds its GPUEngineDriver under.
    | Rebind one here to point an engine name at a different package without
    | code. Missing package: the container's own not-found exception.
    |
    */
    'engines' => [
        'metal' => ['alias' => 'gpu.metal'],
        'opengl' => ['alias' => 'gpu.opengl'],
        'vulkan' => ['alias' => 'gpu.vulkan'],
        'sdl3' => ['alias' => 'gpu.sdl3'],
    ],
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Stage Host
    |--------------------------------------------------------------------------
    |
    | The window maker Stage::open() uses when the sketch names none.
    | Available options: 'appkit', 'sdl3', 'glfw' (reserved)
    |
    */
    'default' => env('STAGE_HOST', device_os_family() === 'mac' ? 'appkit' : 'sdl3'),

    /*
    |--------------------------------------------------------------------------
    | Host aliases
    |--------------------------------------------------------------------------
    |
    | The container alias each host package binds its StageSession under.
    | Rebind one here to point a host name at a different package without
    | code. Missing package: the container's own not-found exception.
    |
    */
    'hosts' => [
        'appkit' => ['alias' => 'stage.appkit'],
        'sdl3' => ['alias' => 'stage.sdl3'],
        'glfw' => ['alias' => 'stage.glfw'],
    ],

    /*
    |--------------------------------------------------------------------------
    | CPU stage renderer
    |--------------------------------------------------------------------------
    |
    | Which renderer a host uses to put a CPU canvas on screen. 'software' is
    | the point of a CPU stage: no GPU anywhere in the path. Name another of
    | the host's drivers, or null to let it choose an accelerated one.
    |
    */
    'cpu_renderer' => env('STAGE_CPU_RENDERER', 'software'),

    /*
    |--------------------------------------------------------------------------
    | CPU stage fit
    |--------------------------------------------------------------------------
    |
    | How a canvas is scaled into a differently sized window when the sketch
    | names no fit. Options: 'stretch', 'letterbox', 'integer_scale', 'overscan'.
    | Whole-number zoom keeps panel pixels square and crisp.
    |
    */
    'cpu_fit' => env('STAGE_CPU_FIT', 'integer_scale'),
];

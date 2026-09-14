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
];

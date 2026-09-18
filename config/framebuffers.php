<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Framebuffer storage driver
    |--------------------------------------------------------------------------
    |
    | Where the CPU engines' pixel bytes live. 'php' is in-house and always
    | available. 'native' needs ext-fb and jovian/fb installed; with them the
    | five buffer kinds, their byte layouts and RGBA8 transcoding run in C.
    |
    */
    'default' => env('FRAMEBUFFER_DRIVER', 'php'),

    /*
    |--------------------------------------------------------------------------
    | Driver aliases
    |--------------------------------------------------------------------------
    |
    | The container alias each driver is bound under. Rebind one here to point
    | a driver name at another package without code. A missing package is the
    | container's own not-found exception.
    |
    */
    'drivers' => [
        'php' => ['alias' => 'framebuffer.php'],
        'native' => ['alias' => 'framebuffer.native'],
    ],
];

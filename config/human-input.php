<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Input Engine
    |--------------------------------------------------------------------------
    |
    | Where HumanInput::keyboard() and ::mouse() read from when the sketch
    | names no engine. sdl3 sees SDL stages on every OS; appkit and gtk see
    | native windows. Available options: 'sdl3', 'appkit', 'gtk'
    |
    */
    'default' => env('INPUT_ENGINE', 'sdl3'),

    /*
    |--------------------------------------------------------------------------
    | Engine aliases
    |--------------------------------------------------------------------------
    |
    | The container alias each engine package binds its InputEngineDriver
    | under. Rebind one here to point an engine name at a different package
    | without code. Missing package: the container's own not-found exception.
    |
    */
    'engines' => [
        'sdl3' => ['alias' => 'input.sdl3'],
        'appkit' => ['alias' => 'input.appkit'],
        'gtk' => ['alias' => 'input.gtk'],
    ],
];

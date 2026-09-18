<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default face
    |--------------------------------------------------------------------------
    |
    | Used when Fonts::face() is called without a slug.
    |
    */
    'default' => env('FONT_FACE', 'classic'),

    /*
    |--------------------------------------------------------------------------
    | Faces
    |--------------------------------------------------------------------------
    |
    | slug => ['class' => a GFXFont subclass, 'enabled' => bool]. Companion
    | packages (venusian/letterhead) extend the registry at boot; entries here
    | are for app-local faces from make:font, or to replace classic.
    |
    */
    'faces' => [
        'classic' => ['class' => Surface\Fonts\ClassicFont::class, 'enabled' => true],
    ],
];

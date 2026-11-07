<?php

return [
    // The face app('fonts')->face() answers when no slug is given.
    'default' => env('FONT_FACE', 'classic'),

    // The TrueType driver. 'extended' is the only one: it needs ext-freetype, ext-harfbuzz and ext-fontconfig.
    'driver' => env('FONT_DRIVER', 'extended'),

    // slug => ['class' => a GFXFont subclass, 'enabled' => bool] for a bitmap face, or
    // slug => ['pattern' => 'sans-serif', 'size' => 13, 'enabled' => bool] for a TrueType face. A companion
    // package extends the registry at boot; entries here are for app-local
    // faces from make:font, or to replace classic.
    'faces' => [
        'classic' => ['class' => Surface\Fonts\ClassicFont::class, 'enabled' => true],
    ],
];

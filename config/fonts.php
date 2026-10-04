<?php

return [
    // The face app('fonts')->face() answers when no slug is given.
    'default' => env('FONT_FACE', 'classic'),

    // slug => ['class' => a GFXFont subclass, 'enabled' => bool]. A companion
    // package extends the registry at boot; entries here are for app-local
    // faces from make:font, or to replace classic.
    'faces' => [
        'classic' => ['class' => Surface\Fonts\ClassicFont::class, 'enabled' => true],
    ],
];

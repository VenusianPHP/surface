<?php

return [
    // The engine app('drawing')->renderer() builds when none is named.
    // 'velvet', the software engine, is built in; an engine package registers its own.
    'default' => 'velvet',

    'velvet' => [
        // 'auto': shapes are painted straight into the framebuffer when both drivers are
        // extended (ext-rasterize into ext-fb, no span strings between them); 'off': every
        // shape's spans go through paintSpans(). The pixels are the same either way.
        // Available: 'auto' | 'off'
        'direct_fill' => env('VELVET_DIRECT_FILL', 'auto'),
    ],
];

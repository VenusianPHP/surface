<?php

return [
    // Where the decoding runs: 'native' reads PNG and JPEG through ext-gd and
    // TIFF in PHP; 'extended' reads all three in C and needs ext-imgdec; 'auto'
    // is extended when ext-imgdec is loaded, native when not.
    // Available drivers: 'auto' | 'native' | 'extended'
    'default' => env('IMAGES_DRIVER', 'auto'),

    // The framebuffer driver decoded images live on: 'auto', 'native',
    // 'extended', or null for the framebuffers default.
    'framebuffers' => null,
];

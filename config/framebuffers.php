<?php

return [
    // Where framebuffer bytes live: 'native' keeps them in PHP and is always
    // available; 'extended' keeps them in C and needs ext-fb 0.10; 'auto' is
    // extended when ext-fb 0.10 is loaded, native when not.
    // Available drivers: 'auto' | 'native' | 'extended'
    'default' => env('FRAMEBUFFERS_DRIVER', 'auto'),
];

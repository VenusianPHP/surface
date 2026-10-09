<?php

return [
    'toolkit' => [
        'mac' => [
            'default' => 'appkit' // 'appkit', 'gtk' or 'qt'
        ],
        'linux' => [
            'default' => 'gtk' //'gtk' or 'qt'
        ],
    ],

    // Which toolkit stages windows (app('staged-windows')): a window that is one output, no primitives.
    'stage' => [
        'mac' => [
            'default' => 'appkit', // 'appkit', 'sdl3' or 'glfw'
        ],
        'linux' => [
            'default' => 'sdl3', // 'sdl3' or 'glfw'
        ],
    ],
];

<?php

return [
    'about' => [
        'name' => env('APP_NAME', 'Venusian'),
        'version' => env('APP_VERSION'),
        'copyright' => null,
    ],

    // Shown on macOS when none of our windows has focus. Null = empty bar.
    'default_menu' => 'main',

    'menus' => [
        'main' => [
            // macOS renames the first folder to the process name; About and Quit belong here.
            ['label' => 'App', 'items' => [
                ['role' => 'about', 'label' => 'About'],
                ['separator' => true],
                ['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q'],
            ]],
            ['label' => 'View', 'items' => [
                ['id' => 'grid', 'label' => 'Show Grid', 'toggle' => true, 'on' => false],
                ['id' => 'refresh', 'label' => 'Refresh', 'hotkey' => 'r'],
            ]],
        ],
    ],
];
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Window Driver
    |--------------------------------------------------------------------------
    |
    | Used by Window::make() when no driver is passed.
    | Available options: 'mac', 'linux'
    |
    */
    'default' => env('WINDOW_DRIVER', device_os_family()),

    'drivers' => [
        'mac' => [
            'class' => \Surface\NativeWindows\Drivers\AppKitWindowDriver::class,
            'args' => [
                //'app_name' => env('APP_NAME', 'Venusian Surface AppKit App'),
            ]
        ],
        'linux' => [
            'class' => \Surface\NativeWindows\Drivers\GTKWindowDriver::class,
            'args' => [
                //'application_id' => env('GTK_APP_NAME','com.venusian.surface-app'),
                //'application_flags' => 0, //ApplicationFlags::DEFAULT_FLAGS->value,
            ]
        ],
    ]
];
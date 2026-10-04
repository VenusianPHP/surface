<?php

return [
    // Where the geometry runs: 'native' is PHP and always available;
    // 'extended' is C and needs ext-rasterize; 'auto' is extended when
    // ext-rasterize is loaded, native when not. All answer the same spans.
    // Available drivers: 'auto' | 'native' | 'extended'
    'default' => env('RASTERIZE_DRIVER', 'auto'),
];

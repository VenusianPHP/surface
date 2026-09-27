<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default CPU engine by panel kind
    |--------------------------------------------------------------------------
    |
    | Used when EmbeddedDisplays::attach() names no engine. 'refreshing' is a
    | panel that shows its RAM on command (ePaper), 'addressable' one that
    | takes region writes (OLED, TFT), 'whole' one that takes only whole
    | frames (LED strips and matrices). Any name config/cpu.php knows.
    |
    */
    'defaults' => [
        'refreshing' => 'epaper',
        'addressable' => 'dirty',
        'whole' => 'full',
    ],
];

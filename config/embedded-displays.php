<?php

return [
    // The framebuffer kind a display hands out when framebuffer() names none:
    // 'refreshing' for a panel that shows its RAM on command (ePaper),
    // 'addressable' for one that takes region writes (OLED, TFT),
    // 'whole' for one that takes whole frames only (LED matrices).
    // Any of full, dirty, epaper, ring.
    'defaults' => [
        'refreshing' => 'epaper',
        'addressable' => 'dirty',
        'whole' => 'full',
    ],
];

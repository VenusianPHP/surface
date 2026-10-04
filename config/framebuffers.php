<?php

return [
    // Where framebuffer bytes live: 'native' keeps them in PHP and is always
    // available; 'extended' keeps them in C and needs ext-fb.
    'default' => 'native',
];

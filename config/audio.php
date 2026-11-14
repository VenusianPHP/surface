<?php

return [
    // The audio output app('audio')->driver() gives when none is named: the first of
    // these names an audio package has registered. Surface registers none itself.
    'mac' => [
        'default' => ['avkit', 'miniaudio', 'sdl3', 'soloud', 'libsoundio', 'sokol-audio'],
    ],
    'linux' => [
        'default' => ['miniaudio', 'sdl3', 'soloud', 'libsoundio', 'sokol-audio'],
    ],
];

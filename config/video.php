<?php

return [
    // The video driver app('video')->driver() gives when none is named: the first of
    // these names a video package has registered. Surface registers none itself.
    'mac' => [
        'default' => ['avkit', 'ffmpeg', 'gstreamer', 'vlc'],
    ],
    'linux' => [
        'default' => ['ffmpeg', 'gstreamer', 'vlc'],
    ],
];

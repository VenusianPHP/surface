<?php

return [
    // The pad source: one per process, serving game pads to every toolkit's windows and
    // to scripts with none. A name a package registered with
    // app('human-input')->extendPads(), or null for none (circuits only).
    'pads' => [
        'mac' => 'gamecontroller', // 'gamecontroller' or 'sdl3'
        'linux' => 'evdev',        // 'evdev' or 'sdl3'
    ],

    // Generic joysticks (no gamepad mapping) by the name their source reports: axis and
    // button indexes onto Surface's vocabulary. A joystick not listed here reads under
    // the default map: axes 0/1 left stick, 2/3 right stick, 4/5 triggers; buttons 0-3
    // south/east/west/north, 4/5 shoulders, 6/7 back/start; hat 0 the dpad.
    'joysticks' => [
        // 'Thrustmaster T.16000M' => [
        //     'axes' => [0 => 'LEFT_X', 1 => 'LEFT_Y', 2 => 'RIGHT_X', 3 => 'LEFT_TRIGGER'],
        //     'buttons' => [0 => 'SOUTH', 1 => 'EAST', 2 => 'WEST', 3 => 'NORTH'],
        //     'hat' => true,
        // ],
    ],
];

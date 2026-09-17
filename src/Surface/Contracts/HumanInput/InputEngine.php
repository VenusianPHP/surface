<?php

namespace Surface\Contracts\HumanInput;

/** The input sources a driver can come from. Resolution is by container alias (input.<value>), never by class. */
enum InputEngine: string
{
    case SDL3 = 'sdl3';
    case APPKIT = 'appkit';
    case GTK = 'gtk';
}

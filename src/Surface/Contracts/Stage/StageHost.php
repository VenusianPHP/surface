<?php

namespace Surface\Contracts\Stage;

/** The window makers a stage can come from. Resolution is by container alias (stage.<value>), never by class. */
enum StageHost: string
{
    case APPKIT = 'appkit';
    case SDL3 = 'sdl3';
    case GLFW = 'glfw';
}

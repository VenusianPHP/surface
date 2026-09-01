<?php

namespace Surface\NativeWindows\Enums;

/**
 * Engine-neutral names for menu behaviour the OS already knows how to do.
 *
 * A sketch states intent; each engine package owns the translation table —
 * AppKit maps a role to a selector, GTK to its own action. A platform with
 * no honest equivalent skips the item rather than faking it.
 */
enum MenuRole: string
{
    case QUIT = 'quit';
    case ABOUT = 'about';
    case HIDE = 'hide';
    case CLOSE_WINDOW = 'close-window';
    case MINIMIZE = 'minimize';
    case FULLSCREEN = 'fullscreen';
}

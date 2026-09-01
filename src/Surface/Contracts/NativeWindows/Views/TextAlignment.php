<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * Where text sits inside its own frame. Engine-neutral; AppKit maps to
 * NSTextAlignment, GTK to justification plus xalign.
 */
enum TextAlignment: string
{
    case LEFT = 'left';
    case CENTER = 'center';
    case RIGHT = 'right';
}

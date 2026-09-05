<?php

namespace Surface\NativeWindows\Components;

/**
 * Which edge a Drawer was placed against. Recorded for the sketch —
 * Surface does not dock or move the root.
 */
enum DrawerSide: string
{
    case LEFT = 'left';
    case RIGHT = 'right';
    case TOP = 'top';
    case BOTTOM = 'bottom';
}

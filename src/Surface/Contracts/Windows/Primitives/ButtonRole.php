<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * A button's role in its window. Return or keypad Enter clicks the DEFAULT button, Esc clicks
 * the CANCEL button, wherever the focus is; Return in a focused text area inserts a line.
 */
enum ButtonRole: string
{
    case DEFAULT = 'default';
    case CANCEL = 'cancel';
}

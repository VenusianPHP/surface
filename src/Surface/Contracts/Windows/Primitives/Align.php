<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * Where a primitive sits inside a slot larger than it needs.
 */
enum Align: string
{
    case START = 'start';
    case CENTER = 'center';
    case END = 'end';
    case FILL = 'fill';
}

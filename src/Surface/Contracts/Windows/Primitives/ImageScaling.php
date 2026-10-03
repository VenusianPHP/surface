<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * How an image meets its view: FIT keeps the aspect inside the bounds, FILL keeps
 * the aspect and covers them, CENTER draws at natural size, STRETCH ignores the aspect.
 */
enum ImageScaling: string
{
    case FIT = 'fit';
    case FILL = 'fill';
    case CENTER = 'center';
    case STRETCH = 'stretch';
}

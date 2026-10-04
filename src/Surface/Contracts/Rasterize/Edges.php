<?php

namespace Surface\Contracts\Rasterize;

/** How a shape's boundary meets the pixel grid. */
enum Edges: string
{
    /** A pixel is in when its centre is inside: coverage 255 or nothing. */
    case HARD = 'hard';

    /** A pixel's coverage is how much of it the shape covers: 1..255. */
    case ANTIALIASED = 'antialiased';
}

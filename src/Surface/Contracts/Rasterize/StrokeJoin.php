<?php

namespace Surface\Contracts\Rasterize;

/** How a stroke turns a corner: SVG stroke-linejoin, Canvas 2D lineJoin, Skia SkPaint::Join. */
enum StrokeJoin: string
{
    /** A sharp point, cut to a bevel where it would pass the miter limit. */
    case MITER = 'miter';

    /** An arc about the corner. */
    case ROUND = 'round';

    /** The corner cut flat. */
    case BEVEL = 'bevel';
}

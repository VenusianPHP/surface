<?php

namespace Surface\Contracts\Rasterize;

/** How an open stroke ends: SVG stroke-linecap, Canvas 2D lineCap, Skia SkPaint::Cap. */
enum StrokeCap: string
{
    /** Flat, at the end point. */
    case BUTT = 'butt';

    /** A half circle, half the width past the end point. */
    case ROUND = 'round';

    /** Flat, half the width past the end point. */
    case SQUARE = 'square';
}

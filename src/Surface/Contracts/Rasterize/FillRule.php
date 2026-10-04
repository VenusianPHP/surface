<?php

namespace Surface\Contracts\Rasterize;

/** Which points of a path are inside, counted by how its contours wind around them. */
enum FillRule: string
{
    /** Inside where the contours wind a non-zero number of times: overlaps unite, an opposite-wound contour cuts a hole. */
    case NON_ZERO = 'non-zero';

    /** Inside where an odd number of contour edges lie to one side: every overlap is a hole. */
    case EVEN_ODD = 'even-odd';
}

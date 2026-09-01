<?php

namespace Surface\NativeWindows\Enums;

/**
 * How a view's origin is decided when the content resizes.
 */
enum PlacementRule: string
{
    /** Stay at the stored x,y — position:absolute. */
    case ABSOLUTE = 'absolute';
    /** Re-centre inside the content on every layout. */
    case CENTER = 'center';
    /** Re-centre horizontally, keep the stored y — a card anchored from the top. */
    case CENTER_X = 'center-x';
}

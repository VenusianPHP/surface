<?php

namespace Surface\NativeWindows\Enums;

/**
 * How a view's extent is decided when the content resizes.
 */
enum SizeRule: string
{
    /** Keep the stored width and height. */
    case FIXED = 'fixed';
    /** Re-measure the content's natural size on every layout. */
    case NATURAL = 'natural';
    /** Keep the stored width, re-measure the height for text flowed at it. */
    case WRAP = 'wrap';
}

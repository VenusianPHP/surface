<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * Where a button's image sits: LEADING before the label, ABOVE it, ONLY the image (the label
 * stays the accessible name).
 */
enum ImagePosition: string
{
    case LEADING = 'leading';
    case ABOVE = 'above';
    case ONLY = 'only';
}

<?php

namespace Surface\NativeWindows\Components;

/**
 * Intended silhouette of a Skeleton. CIRCLE is still a square group —
 * neither engine clips to an oval today.
 */
enum SkeletonShape: string
{
    case RECTANGLE = 'rectangle';
    case CIRCLE = 'circle';
}

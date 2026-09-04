<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A thin dividing line. Orientation is decided at conjure time from the
 * frame's aspect — wider than tall is horizontal — and does not change on
 * later placement.
 */
interface OSSeparator extends OSView
{
    public function isHorizontal(): bool;
}

<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\OSSeparator;
use Surface\NativeWindows\Windowable;

/**
 * A thin dividing line. Orientation is fixed at conjure time from the
 * frame's aspect — wider than tall is horizontal — because neither engine
 * can honestly flip a minted separator.
 */
abstract class Separator extends View implements OSSeparator
{
    public function __construct(
        string $name,
        Windowable $window,
        protected bool $horizontal,
    ) {
        parent::__construct($name, $window);
    }

    public function isHorizontal(): bool
    {
        return $this->horizontal;
    }
}

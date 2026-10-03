<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKSeparator as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;

abstract class TKSeparator extends TKPrimitive implements PrimitiveContract
{
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected readonly bool $horizontal,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function isHorizontal(): bool
    {
        return $this->horizontal;
    }
}

<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * A horizontal or vertical rule; the orientation is fixed at creation. Posts no mail.
 */
interface TKSeparator extends TKPrimitive
{
    /**
     * @return bool
     */
    public function isHorizontal(): bool;
}

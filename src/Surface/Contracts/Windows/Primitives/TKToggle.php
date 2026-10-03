<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * An on/off switch. Posts Toggled. Not available on qt.
 */
interface TKToggle extends TKPrimitive
{
    /**
     * @return bool
     */
    public function isOn(): bool;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param bool $on
     * @return $this
     */
    public function setOn(bool $on): static;
}

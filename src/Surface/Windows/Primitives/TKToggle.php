<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKToggle as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;

/**
 * The driver posts Toggled from the native signal, right after nativeToggled().
 */
abstract class TKToggle extends TKPrimitive implements PrimitiveContract
{
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected bool $on,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function isOn(): bool
    {
        return $this->on;
    }

    public function setOn(bool $on): static
    {
        $this->live();
        $this->on = $on;
        $this->applyOn($on);

        return $this;
    }

    /**
     * Engine callback: record the user's toggle, post nothing, write nothing back.
     *
     * @param bool $on
     * @return void
     */
    public function nativeToggled(bool $on): void
    {
        $this->on = $on;
    }

    /**
     * @param bool $on
     * @return void
     */
    abstract protected function applyOn(bool $on): void;
}

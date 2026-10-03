<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKToggleButton as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;

/**
 * The driver posts Toggled from the native signal, right after nativeToggled().
 */
abstract class TKToggleButton extends TKPrimitive implements PrimitiveContract
{
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $label,
        protected bool $pressed,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->live();
        $this->label = $label;
        $this->applyLabel($label);

        return $this;
    }

    public function isPressed(): bool
    {
        return $this->pressed;
    }

    public function setPressed(bool $pressed): static
    {
        $this->live();
        $this->pressed = $pressed;
        $this->applyPressed($pressed);

        return $this;
    }

    /**
     * Engine callback: record the user's toggle, post nothing, write nothing back.
     *
     * @param bool $pressed
     * @return void
     */
    public function nativeToggled(bool $pressed): void
    {
        $this->pressed = $pressed;
    }

    /**
     * @param string $label
     * @return void
     */
    abstract protected function applyLabel(string $label): void;

    /**
     * @param bool $pressed
     * @return void
     */
    abstract protected function applyPressed(bool $pressed): void;
}

<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKCheckbox as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;

/**
 * The driver posts Toggled from the native signal, right after nativeToggled().
 */
abstract class TKCheckbox extends TKPrimitive implements PrimitiveContract
{
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $label,
        protected bool $checked,
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

    public function isChecked(): bool
    {
        return $this->checked;
    }

    public function setChecked(bool $checked): static
    {
        $this->live();
        $this->checked = $checked;
        $this->applyChecked($checked);

        return $this;
    }

    /**
     * Engine callback: record the user's toggle, post nothing, write nothing back.
     *
     * @param bool $checked
     * @return void
     */
    public function nativeToggled(bool $checked): void
    {
        $this->checked = $checked;
    }

    /**
     * @param string $label
     * @return void
     */
    abstract protected function applyLabel(string $label): void;

    /**
     * @param bool $checked
     * @return void
     */
    abstract protected function applyChecked(bool $checked): void;
}

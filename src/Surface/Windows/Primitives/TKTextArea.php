<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKTextArea as PrimitiveContract;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\NutsAndBolts\Color;

/**
 * The driver posts TextChanged from the native signal, right after nativeValueChanged().
 */
abstract class TKTextArea extends TKPrimitive implements PrimitiveContract
{
    protected ?FontSpec $font = null;

    protected ?Color $text_color = null;

    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $value,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->live();
        $this->value = $value;
        $this->applyValue($value);

        return $this;
    }

    public function setFont(FontSpec $font): static
    {
        $this->live();
        $this->font = $font;
        $this->applyFont($font);

        return $this;
    }

    public function setTextColor(?Color $color): static
    {
        $this->live();
        $this->text_color = $color;
        $this->applyTextColor($color);

        return $this;
    }

    /**
     * Engine callback: record what the user typed, post nothing, write nothing back.
     *
     * @param string $value
     * @return void
     */
    public function nativeValueChanged(string $value): void
    {
        $this->value = $value;
    }

    /**
     * @param string $value
     * @return void
     */
    abstract protected function applyValue(string $value): void;

    /**
     * @param FontSpec $font
     * @return void
     */
    abstract protected function applyFont(FontSpec $font): void;

    /**
     * @param Color|null $color null restores the toolkit's own
     * @return void
     */
    abstract protected function applyTextColor(?Color $color): void;
}

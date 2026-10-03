<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKTextInput as PrimitiveContract;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\NutsAndBolts\Color;

/**
 * The driver posts TextChanged / TextSubmitted from the native signals, right after
 * nativeValueChanged() records the value.
 */
abstract class TKTextInput extends TKPrimitive implements PrimitiveContract
{
    protected ?FontSpec $font = null;

    protected ?Color $text_color = null;

    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $value,
        protected ?string $placeholder,
        protected readonly bool $secret,
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

    public function placeholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(?string $placeholder): static
    {
        $this->live();
        $this->placeholder = $placeholder;
        $this->applyPlaceholder($placeholder);

        return $this;
    }

    public function isSecret(): bool
    {
        return $this->secret;
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
     * @param string|null $placeholder
     * @return void
     */
    abstract protected function applyPlaceholder(?string $placeholder): void;

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

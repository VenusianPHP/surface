<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKButton as PrimitiveContract;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\NutsAndBolts\Color;

/**
 * The driver posts ButtonClicked from the native click; nothing here posts.
 */
abstract class TKButton extends TKPrimitive implements PrimitiveContract
{
    protected ?FontSpec $font = null;

    protected ?Color $text_color = null;

    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $label,
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
     * @param string $label
     * @return void
     */
    abstract protected function applyLabel(string $label): void;

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

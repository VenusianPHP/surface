<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKLabel as PrimitiveContract;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\TextAlignment;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\NutsAndBolts\Color;

abstract class TKLabel extends TKPrimitive implements PrimitiveContract
{
    protected bool $wrap = false;

    protected TextAlignment $alignment = TextAlignment::LEFT;

    protected ?FontSpec $font = null;

    protected ?Color $text_color = null;

    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $text,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function text(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->live();
        $this->text = $text;
        $this->applyText($text);

        return $this;
    }

    public function setWrap(bool $wrap): static
    {
        $this->live();
        $this->wrap = $wrap;
        $this->applyWrap($wrap);

        return $this;
    }

    public function setAlignment(TextAlignment $alignment): static
    {
        $this->live();
        $this->alignment = $alignment;
        $this->applyAlignment($alignment);

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
     * @param string $text
     * @return void
     */
    abstract protected function applyText(string $text): void;

    /**
     * @param bool $wrap
     * @return void
     */
    abstract protected function applyWrap(bool $wrap): void;

    /**
     * @param TextAlignment $alignment
     * @return void
     */
    abstract protected function applyAlignment(TextAlignment $alignment): void;

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

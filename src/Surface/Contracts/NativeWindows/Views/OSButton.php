<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A push button.
 */
interface OSButton extends OSView
{
    public function label(): ?string;

    public function setLabel(string $label): static;

    /** Hook invoked when the button is clicked, during the pump that delivers it. */
    public function onClick(callable $hook): static;

    public function setTextColor(Color $color): static;

    public function setFont(float $size, FontWeight $weight = FontWeight::REGULAR, ?string $family = null): static;

    /** A small CSS declaration list; each engine applies what it can and ignores the rest. */
    public function textCSS(string $css): static;
}

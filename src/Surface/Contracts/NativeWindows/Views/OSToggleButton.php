<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A button that stays pressed until pressed again.
 */
interface OSToggleButton extends OSView
{
    public function label(): ?string;

    public function setLabel(string $label): static;

    public function isPressed(): bool;

    public function setPressed(bool $pressed): static;

    /** Hook invoked when the user presses it, during the pump. Receives the new pressed state. */
    public function onToggle(callable $hook): static;

    public function setEnabled(bool $enabled): static;

    public function isEnabled(): bool;

    public function setTextColor(Color $color): static;

    public function setFont(float $size, FontWeight $weight = FontWeight::REGULAR, ?string $family = null): static;

    public function textCSS(string $css): static;
}

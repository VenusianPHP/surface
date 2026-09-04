<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A labelled checkbox.
 */
interface OSCheckbox extends OSView
{
    public function label(): ?string;

    public function setLabel(string $label): static;

    public function isChecked(): bool;

    public function setChecked(bool $checked): static;

    /** Hook invoked when the user toggles it, during the pump. Receives the new checked state. */
    public function onToggle(callable $hook): static;

    public function setEnabled(bool $enabled): static;

    public function isEnabled(): bool;

    public function setTextColor(Color $color): static;

    public function setFont(float $size, FontWeight $weight = FontWeight::REGULAR, ?string $family = null): static;

    public function textCSS(string $css): static;
}

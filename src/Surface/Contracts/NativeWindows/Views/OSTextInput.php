<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A single-line text field. A secret field masks its glyphs; engines with
 * no honest placeholder path for a secret field ignore the placeholder,
 * stated in their own code.
 */
interface OSTextInput extends OSView
{
    public function value(): string;

    public function setValue(string $value): static;

    public function placeholder(): ?string;

    public function setPlaceholder(string $placeholder): static;

    /** Hook invoked on every edit, during the pump that delivers it. Receives the new value. */
    public function onChange(callable $hook): static;

    /** Hook invoked when the user submits (Enter), during the pump. Receives the value. */
    public function onSubmit(callable $hook): static;

    public function setEnabled(bool $enabled): static;

    public function isEnabled(): bool;

    public function setTextColor(Color $color): static;

    public function setFont(float $size, FontWeight $weight = FontWeight::REGULAR, ?string $family = null): static;

    public function textCSS(string $css): static;
}

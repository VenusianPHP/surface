<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A multi-line, scrolling text editor. value() answers the text the
 * engine holds — every edit reads the native buffer back.
 */
interface OSTextArea extends OSView
{
    public function value(): string;

    public function setValue(string $value): static;

    public function isEditable(): bool;

    public function setEditable(bool $editable): static;

    /**
     * Hook invoked on every edit, during the pump that delivers it.
     * Receives the new value.
     */
    public function onChange(callable $hook): static;

    public function setTextColor(Color $color): static;

    public function setFont(float $size, FontWeight $weight = FontWeight::REGULAR, ?string $family = null): static;

    public function textCSS(string $css): static;
}

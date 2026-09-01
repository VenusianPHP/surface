<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A static text node.
 */
interface OSLabel extends OSView
{
    public function text(): string;

    public function setText(string $text): static;

    /** Align the text inside the label's own frame. */
    public function align(TextAlignment $alignment): static;

    /** Fix the width, word-wrap the text, take the measured height. */
    public function wrap(int $width): static;

    public function setTextColor(Color $color): static;

    public function setFont(float $size, FontWeight $weight = FontWeight::REGULAR, ?string $family = null): static;

    /** A small CSS declaration list; each engine applies what it can and ignores the rest. */
    public function textCSS(string $css): static;
}

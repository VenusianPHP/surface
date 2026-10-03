<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\TextAlignment;
use Surface\NutsAndBolts\Color;

/**
 * Static text. Posts no mail.
 */
interface TKLabel extends TKPrimitive
{
    /**
     * @return string
     */
    public function text(): string;

    /**
     * @param string $text
     * @return $this
     */
    public function setText(string $text): static;

    /**
     * Wrap at the allocated width instead of growing.
     * @param bool $wrap
     * @return $this
     */
    public function setWrap(bool $wrap): static;

    /**
     * @param TextAlignment $alignment
     * @return $this
     */
    public function setAlignment(TextAlignment $alignment): static;

    /**
     * @param FontSpec $font
     * @return $this
     */
    public function setFont(FontSpec $font): static;

    /**
     * @param Color|null $color null restores the toolkit's own
     * @return $this
     */
    public function setTextColor(?Color $color): static;
}

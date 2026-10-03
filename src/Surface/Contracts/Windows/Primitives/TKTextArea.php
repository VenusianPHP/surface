<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\NutsAndBolts\Color;

/**
 * Multi-line editable text. Posts TextChanged as the user types.
 */
interface TKTextArea extends TKPrimitive
{
    /**
     * @return string
     */
    public function value(): string;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param string $value
     * @return $this
     */
    public function setValue(string $value): static;

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

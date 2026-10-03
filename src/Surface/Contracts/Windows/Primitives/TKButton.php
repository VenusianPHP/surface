<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\NutsAndBolts\Color;

/**
 * A push button. Posts ButtonClicked.
 */
interface TKButton extends TKPrimitive
{
    /**
     * @return string
     */
    public function label(): string;

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): static;

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

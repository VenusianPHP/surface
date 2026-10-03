<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\NutsAndBolts\Color;

/**
 * One line of editable text. Posts TextChanged as the user types and TextSubmitted on return.
 */
interface TKTextInput extends TKPrimitive
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
     * @return string|null
     */
    public function placeholder(): ?string;

    /**
     * @param string|null $placeholder
     * @return $this
     */
    public function setPlaceholder(?string $placeholder): static;

    /**
     * Whether the entry masks what is typed; fixed at creation.
     * @return bool
     */
    public function isSecret(): bool;

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

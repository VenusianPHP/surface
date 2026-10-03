<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * A labelled check box. Posts Toggled.
 */
interface TKCheckbox extends TKPrimitive
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
     * @return bool
     */
    public function isChecked(): bool;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param bool $checked
     * @return $this
     */
    public function setChecked(bool $checked): static;
}

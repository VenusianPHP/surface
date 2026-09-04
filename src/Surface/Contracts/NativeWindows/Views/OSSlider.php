<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A continuous slider over a float range.
 */
interface OSSlider extends OSView
{
    public function min(): float;

    public function max(): float;

    public function value(): float;

    /** Write a value, clamped into the range. */
    public function setValue(float $value): static;

    public function setRange(float $min, float $max): static;

    /** Hook invoked as the thumb moves, during the pump. Receives the new value. */
    public function onChange(callable $hook): static;

    public function setEnabled(bool $enabled): static;

    public function isEnabled(): bool;
}

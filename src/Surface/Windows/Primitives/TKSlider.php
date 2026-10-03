<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKSlider as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * The value always lies in [min, max]. The driver posts ValueChanged from the native
 * signal, right after nativeValueChanged().
 */
abstract class TKSlider extends TKPrimitive implements PrimitiveContract
{
    /**
     * @throws WindowException When the name is not valid or min is not below max.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected float $min,
        protected float $max,
        protected float $value,
    ) {
        self::guardRange($min, $max);
        $this->value = self::clamp(self::guardValue($value), $min, $max);
        parent::__construct($name, $window, $parent, $placement);
    }

    public function value(): float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->live();
        $this->value = self::clamp(self::guardValue($value), $this->min, $this->max);
        $this->applyValue($this->value);

        return $this;
    }

    public function min(): float
    {
        return $this->min;
    }

    public function max(): float
    {
        return $this->max;
    }

    public function setRange(float $min, float $max): static
    {
        $this->live();
        self::guardRange($min, $max);
        $this->min = $min;
        $this->max = $max;
        $this->applyRange($min, $max);

        $clamped = self::clamp($this->value, $min, $max);
        if ($clamped !== $this->value) {
            $this->value = $clamped;
            $this->applyValue($clamped);
        }

        return $this;
    }

    /**
     * Engine callback: record the user's value, post nothing, write nothing back.
     *
     * @param float $value
     * @return void
     */
    public function nativeValueChanged(float $value): void
    {
        $this->value = $value;
    }

    /**
     * @param float $min
     * @param float $max
     * @return void
     * @throws WindowException When min or max is not finite, or min is not below max.
     */
    protected static function guardRange(float $min, float $max): void
    {
        if (! is_finite($min) || ! is_finite($max)) {
            throw new WindowException("A slider range must be finite, got {$min}..{$max}.");
        }
        if ($min >= $max) {
            throw new WindowException("A slider's min must be below its max, got {$min}..{$max}.");
        }
    }

    /**
     * @param float $value
     * @return float
     * @throws WindowException When the value is not finite.
     */
    protected static function guardValue(float $value): float
    {
        if (! is_finite($value)) {
            throw new WindowException("A slider value must be finite, got {$value}.");
        }

        return $value;
    }

    protected static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * @param float $value already clamped
     * @return void
     */
    abstract protected function applyValue(float $value): void;

    /**
     * @param float $min
     * @param float $max
     * @return void
     */
    abstract protected function applyRange(float $min, float $max): void;
}

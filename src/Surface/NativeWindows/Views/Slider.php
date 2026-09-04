<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSSlider;
use Surface\NativeWindows\Windowable;

/**
 * A continuous slider over a float range. Engines wire their native
 * value-changed signal into fireChanged(); every move rides the dock as
 * VALUE_CHANGED (`<window>.<name>.changed`).
 */
abstract class Slider extends View implements OSSlider
{
    use HasEnabledState;

    protected ?Closure $on_change = null;

    public function __construct(
        string $name,
        Windowable $window,
        protected float $min,
        protected float $max,
        protected float $value,
    ) {
        parent::__construct($name, $window);
        $this->value = $this->clamp($value);
    }

    public function min(): float
    {
        return $this->min;
    }

    public function max(): float
    {
        return $this->max;
    }

    public function value(): float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->value = $this->clamp($value);
        $this->applyValue($this->value);

        return $this;
    }

    public function setRange(float $min, float $max): static
    {
        $this->min = $min;
        $this->max = $max;
        $this->applyRange($min, $max);
        // The old value may sit outside the new range — re-clamp and write.
        $this->setValue($this->value);

        return $this;
    }

    public function onChange(callable $hook): static
    {
        $this->on_change = $hook(...);

        return $this;
    }

    /**
     * Engine callbacks land here with the value read from the native
     * control. Pushes VALUE_CHANGED, then invokes the hook; safe with no
     * hook and no sink.
     * @return void
     */
    protected function fireChanged(float $value): void
    {
        $this->value = $this->clamp($value);
        $this->window->emitViewEvent(SurfaceEventType::VALUE_CHANGED, $this->name, ['value' => $this->value]);

        if (! is_null($this->on_change)) {
            ($this->on_change)($this->value);
        }
    }

    protected function clamp(float $value): float
    {
        return max($this->min, min($this->max, $value));
    }

    /**
     * Write the value to the native control.
     * @return void
     */
    abstract protected function applyValue(float $value): void;

    /**
     * Write the range to the native control.
     * @return void
     */
    abstract protected function applyRange(float $min, float $max): void;
}

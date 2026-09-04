<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSSlider;
use Surface\NativeWindows\Windowable;

/**
 * A thin Component wrap over a continuous slider. Value, range, and
 * change hook delegate to the inner slider.
 *
 * Parts: `slider`.
 */
class Slider extends Component
{
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected float $min,
        protected float $max,
        protected float $value,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('slider', $this->root->slider(
            $this->partName('slider'),
            $this->min,
            $this->max,
            $this->value,
            0,
            0,
            1,
            1,
        ));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $this->parts['slider']->place(0, 0, $width, $height);
    }

    /** The wrapped slider, for anything not delegated below. */
    public function slider(): OSSlider
    {
        /** @var OSSlider */
        return $this->parts['slider'];
    }

    public function value(): float
    {
        return $this->slider()->value();
    }

    public function setValue(float $value): static
    {
        $this->slider()->setValue($value);

        return $this;
    }

    public function min(): float
    {
        return $this->slider()->min();
    }

    public function max(): float
    {
        return $this->slider()->max();
    }

    public function setRange(float $min, float $max): static
    {
        $this->slider()->setRange($min, $max);

        return $this;
    }

    public function onChange(callable $hook): static
    {
        $this->slider()->onChange($hook);

        return $this;
    }
}

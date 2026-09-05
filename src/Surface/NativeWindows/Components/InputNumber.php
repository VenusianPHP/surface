<?php

namespace Surface\NativeWindows\Components;

use Closure;
use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSTextInput;
use Surface\NativeWindows\Windowable;

/**
 * A numeric field with stacked stepper buttons. The input may be mid-edit;
 * non-numeric keystrokes leave value() alone. Programmatic setValue is
 * silent; up/down clicks and numeric engine edits fire onChange.
 *
 * Parts: `input`, `up`, `down`. Null min/max means unbounded.
 */
class InputNumber extends Component
{
    protected const STEPPER = 22;

    protected ?Closure $on_change = null;

    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected float $value = 0.0,
        protected ?float $min = null,
        protected ?float $max = null,
        protected float $step = 1.0,
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->value = $this->clamp($this->value);

        $input = $this->root->textInput($this->partName('input'), (string) $this->value, 0, 0, 1, 1);
        $input->onChange(function (string $text): void {
            if (! is_numeric($text)) {
                return;
            }

            $this->value = $this->clamp((float) $text);
            $this->fireChange();
        });
        $this->register('input', $input);

        $up = $this->root->button($this->partName('up'), '+', 0, 0, 1, 1);
        $up->onClick(function (): void {
            $this->nudge($this->step);
        });
        $this->register('up', $up);

        $down = $this->root->button($this->partName('down'), '-', 0, 0, 1, 1);
        $down->onClick(function (): void {
            $this->nudge(-$this->step);
        });
        $this->register('down', $down);
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $column = self::STEPPER;
        $input_width = max(0, $width - $column);

        $this->parts['input']->place(0, 0, $input_width, $height);
        $this->parts['up']->place($input_width, 0, $column, $column);
        $this->parts['down']->place($input_width, $column, $column, $column);
    }

    public function value(): float
    {
        return $this->value;
    }

    /** Silent: writes the number as the input string and does not fire onChange. */
    public function setValue(float $value): static
    {
        $this->value = $this->clamp($value);
        $this->input()->setValue((string) $this->value);

        return $this;
    }

    public function onChange(callable $hook): static
    {
        $this->on_change = $hook(...);

        return $this;
    }

    protected function nudge(float $delta): void
    {
        $this->value = $this->clamp($this->value + $delta);
        $this->input()->setValue((string) $this->value);
        $this->fireChange();
    }

    protected function clamp(float $value): float
    {
        if (! is_null($this->min)) {
            $value = max($this->min, $value);
        }

        if (! is_null($this->max)) {
            $value = min($this->max, $value);
        }

        return $value;
    }

    protected function fireChange(): void
    {
        if (! is_null($this->on_change)) {
            ($this->on_change)($this->value);
        }
    }

    protected function input(): OSTextInput
    {
        /** @var OSTextInput */
        return $this->parts['input'];
    }
}

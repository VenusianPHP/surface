<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Slider;

final class FakeSlider extends Slider
{
    use FakeControlCalls;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        float $min,
        float $max,
        public float $current,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame, $min, $max);
    }

    protected function nativeSetRange(float $min, float $max): void
    {
        $this->log->record('setRange', $this->pointer(), $min, $max);
    }

    protected function nativeSetValue(float $value): void
    {
        $this->current = $value;
        $this->log->record('setValue', $this->pointer(), $value);
    }

    protected function nativeGetValue(): float
    {
        $this->log->record('getValue', $this->pointer());

        return $this->current;
    }

    protected function nativeOnChange(?Closure $trampoline): void
    {
        $this->log->record('onChange', $this->pointer(), $trampoline);
    }
}

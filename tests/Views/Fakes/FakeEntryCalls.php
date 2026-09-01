<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Closure;
use Surface\NativeWindows\Views\Color;

/** Entry hooks shared by FakeEntry and FakePassword. */
trait FakeEntryCalls
{
    public string $stored = '';

    protected function nativeGetValue(): string
    {
        $this->log->record('getValue', $this->pointer());

        return $this->stored;
    }

    protected function nativeSetValue(string $value): void
    {
        $this->stored = $value;
        $this->log->record('setValue', $this->pointer(), $value);
    }

    protected function nativeSetPlaceholder(string $placeholder): void
    {
        $this->log->record('setPlaceholder', $this->pointer(), $placeholder);
    }

    protected function nativeSetTextColor(Color $color): void
    {
        $this->log->record('setTextColor', $this->pointer(), $color->red, $color->green, $color->blue, $color->alpha);
    }

    protected function nativeOnChange(?Closure $trampoline): void
    {
        $this->log->record('onChange', $this->pointer(), $trampoline);
    }

    protected function nativeOnSubmit(?Closure $trampoline): void
    {
        $this->log->record('onSubmit', $this->pointer(), $trampoline);
    }
}

<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Enums\FontWeight;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Size;

/** Records the hooks every fake control shares. Host class must expose `public readonly CallLog $log`. */
trait FakeControlCalls
{
    public ?Size $natural = null;

    protected function nativeSetEnabled(bool $enabled): void
    {
        $this->log->record('setEnabled', $this->pointer(), $enabled);
    }

    protected function nativeSetFont(string $family, float $size, FontWeight $weight): void
    {
        $this->log->record('setFont', $this->pointer(), $family, $size, $weight);
    }

    protected function nativeSetFrame(Frame $frame): void
    {
        $this->log->record('setFrame', $this->pointer(), $frame->x, $frame->y, $frame->width, $frame->height);
    }

    protected function nativeSetBgColor(Color $color): void
    {
        $this->log->record('setBgColor', $this->pointer(), $color->red, $color->green, $color->blue, $color->alpha);
    }

    protected function nativeDetach(): void
    {
        $this->log->record('detach', $this->pointer());
    }

    protected function nativeMeasure(): Size
    {
        $this->log->record('measure', $this->pointer());

        return $this->natural ?? new Size($this->frame->width, $this->frame->height);
    }
}

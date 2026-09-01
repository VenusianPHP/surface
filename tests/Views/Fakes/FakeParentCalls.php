<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\View;

/** Frame, background, detach and attach — for parents that are not controls. */
trait FakeParentCalls
{
    public bool $loadImages = true;

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

    protected function nativeAttach(View $child): void
    {
        $this->log->record('attach', $this->attachPointer(), $child->pointer());
    }
}

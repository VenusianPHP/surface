<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Enums\FontWeight;
use Surface\NativeWindows\Enums\TextAlignment;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Label;
use Surface\NativeWindows\Views\Size;

final class FakeLabel extends Label
{
    /** What the fake driver reports as the text's natural size. */
    public Size $natural;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
        $this->natural = new Size(100, 20);
    }

    protected function nativeSetText(string $text): void
    {
        $this->log->record('setText', $this->pointer(), $text);
    }

    protected function nativeSetTextColor(Color $color): void
    {
        $this->log->record('setTextColor', $this->pointer(), $color->red, $color->green, $color->blue, $color->alpha);
    }

    protected function nativeSetFont(string $family, float $size, FontWeight $weight): void
    {
        $this->log->record('setFont', $this->pointer(), $family, $size, $weight);
    }

    protected function nativeSetAlignment(TextAlignment $alignment): void
    {
        $this->log->record('setAlignment', $this->pointer(), $alignment);
    }

    protected function nativeMeasure(): Size
    {
        $this->log->record('measure', $this->pointer());

        return $this->natural;
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
}

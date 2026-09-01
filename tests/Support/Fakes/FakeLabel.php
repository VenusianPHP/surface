<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\Views\TextAlignment;
use Surface\NativeWindows\Views\Label;

/** A Label whose engine hooks record instead of touching a toolkit. */
final class FakeLabel extends Label
{
    /** @var list<array{int, int, int, int}> Every frame handed to the engine, in order. */
    public array $applied_frames = [];

    /** @var list<string> */
    public array $applied_texts = [];

    /** @var list<TextAlignment> */
    public array $applied_alignments = [];

    /** @var array{int, int} What measure() answers. */
    public array $natural_size = [100, 20];

    public bool $destroyed = false;

    /** @var list<\Surface\Contracts\NativeWindows\Views\Color> */
    public array $applied_text_colors = [];

    /** @var list<\Surface\Contracts\NativeWindows\Views\FontSpec> */
    public array $applied_fonts = [];

    /** @var list<\Surface\Contracts\NativeWindows\Views\Color> */
    public array $applied_backgrounds = [];

    /** @var list<int> Every wrap width pushed to the engine, in order. */
    public array $applied_wraps = [];

    /** @var list<int> Every width the engine was asked to measure a wrapped height for. */
    public array $measured_wrap_widths = [];

    /** What measureWrappedHeight() answers. */
    public int $wrapped_height = 40;

    protected function applyFrame(int $x, int $y, int $width, int $height): void
    {
        $this->applied_frames[] = [$x, $y, $width, $height];
    }

    protected function measure(): array
    {
        return $this->natural_size;
    }

    protected function applyText(string $text): void
    {
        $this->applied_texts[] = $text;
    }

    protected function applyAlignment(TextAlignment $alignment): void
    {
        $this->applied_alignments[] = $alignment;
    }

    protected function destroyNative(): void
    {
        $this->destroyed = true;
    }

    protected function applyTextColor(\Surface\Contracts\NativeWindows\Views\Color $color): void
    {
        $this->applied_text_colors[] = $color;
    }

    protected function applyFont(\Surface\Contracts\NativeWindows\Views\FontSpec $font): void
    {
        $this->applied_fonts[] = $font;
    }

    protected function applyBackground(\Surface\Contracts\NativeWindows\Views\Color $color): void
    {
        $this->applied_backgrounds[] = $color;
    }

    protected function applyWrap(int $width): void
    {
        $this->applied_wraps[] = $width;
    }

    protected function measureWrappedHeight(int $width): int
    {
        $this->measured_wrap_widths[] = $width;

        return $this->wrapped_height;
    }
}

<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\Button;

/** A Button whose engine hooks record instead of touching a toolkit. */
final class FakeButton extends Button
{
    /** @var list<array{int, int, int, int}> */
    public array $applied_frames = [];

    /** @var list<string> */
    public array $applied_labels = [];

    /** @var array{int, int} */
    public array $natural_size = [80, 30];

    public bool $destroyed = false;

    /** @var list<\Surface\Contracts\NativeWindows\Views\Color> */
    public array $applied_text_colors = [];

    /** @var list<\Surface\Contracts\NativeWindows\Views\FontSpec> */
    public array $applied_fonts = [];

    /** @var list<\Surface\Contracts\NativeWindows\Views\Color> */
    public array $applied_backgrounds = [];

    protected function applyFrame(int $x, int $y, int $width, int $height): void
    {
        $this->applied_frames[] = [$x, $y, $width, $height];
    }

    protected function measure(): array
    {
        return $this->natural_size;
    }

    protected function applyLabel(string $label): void
    {
        $this->applied_labels[] = $label;
    }

    /** @var list<bool> Every enabled write that reached the engine, in order. */
    public array $applied_enabled = [];

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
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

    /** Test door: what the engine's native click callback does. */
    public function click(): void
    {
        $this->fireClick();
    }

    /** @var list<bool> Every visibility write that reached the engine. */
    public array $applied_visible = [];

    protected function applyVisible(bool $visible): void
    {
        $this->applied_visible[] = $visible;
    }
}

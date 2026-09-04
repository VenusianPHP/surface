<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\Views\Color;

/**
 * The engine hooks every fake view shares: frames, measure, background and
 * terminal removal, recorded instead of touching a toolkit.
 */
trait RecordsViewApplies
{
    /** @var list<array{int, int, int, int}> */
    public array $applied_frames = [];

    /** @var array{int, int} */
    public array $natural_size = [80, 30];

    public bool $destroyed = false;

    /** @var list<Color> */
    public array $applied_backgrounds = [];

    protected function applyFrame(int $x, int $y, int $width, int $height): void
    {
        $this->applied_frames[] = [$x, $y, $width, $height];
    }

    protected function measure(): array
    {
        return $this->natural_size;
    }

    protected function destroyNative(): void
    {
        $this->destroyed = true;
    }

    protected function applyBackground(Color $color): void
    {
        $this->applied_backgrounds[] = $color;
    }

    /** @var list<bool> */
    public array $applied_visible = [];

    protected function applyVisible(bool $visible): void
    {
        $this->applied_visible[] = $visible;
    }
}

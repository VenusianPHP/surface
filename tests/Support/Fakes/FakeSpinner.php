<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Spinner;

/** A Spinner whose engine hooks record instead of touching a toolkit. */
final class FakeSpinner extends Spinner
{
    /** @var list<array{int, int, int, int}> Every frame handed to the engine, in order. */
    public array $applied_frames = [];

    /** @var list<bool> Every spinning state pushed to the engine, in order. */
    public array $applied_spinnings = [];

    /** @var array{int, int} What measure() answers. */
    public array $natural_size = [24, 24];

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

    protected function applySpinning(bool $spinning): void
    {
        $this->applied_spinnings[] = $spinning;
    }

    protected function destroyNative(): void
    {
        $this->destroyed = true;
    }

    protected function applyBackground(Color $color): void
    {
        $this->applied_backgrounds[] = $color;
    }
}

<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Image;

/** An Image whose engine hooks record instead of touching a toolkit. */
final class FakeImage extends Image
{
    /** @var list<array{int, int, int, int}> Every frame handed to the engine, in order. */
    public array $applied_frames = [];

    /** @var list<string> Every path pushed to the engine, in order. */
    public array $applied_paths = [];

    /** @var array{int, int} What measure() answers. */
    public array $natural_size = [64, 64];

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

    protected function applyPath(string $path): void
    {
        $this->applied_paths[] = $path;
    }

    protected function destroyNative(): void
    {
        $this->destroyed = true;
    }

    protected function applyBackground(Color $color): void
    {
        $this->applied_backgrounds[] = $color;
    }

    /** @var list<bool> Every visibility write that reached the engine. */
    public array $applied_visible = [];

    protected function applyVisible(bool $visible): void
    {
        $this->applied_visible[] = $visible;
    }
}

<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Video;

/** A Video whose engine hooks record instead of touching a toolkit. */
final class FakeVideo extends Video
{
    /** @var list<array{int, int, int, int}> Every frame handed to the engine, in order. */
    public array $applied_frames = [];

    /** @var list<string> Every path pushed to the engine, in order. */
    public array $applied_paths = [];

    /** @var list<bool> Every playing state pushed to the engine, in order. */
    public array $applied_playings = [];

    /** @var list<bool> Every muted state pushed to the engine, in order. */
    public array $applied_muteds = [];

    /** @var array{int, int} What measure() answers. */
    public array $natural_size = [320, 240];

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

    protected function applyPlaying(bool $playing): void
    {
        $this->applied_playings[] = $playing;
    }

    protected function applyMuted(bool $muted): void
    {
        $this->applied_muteds[] = $muted;
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

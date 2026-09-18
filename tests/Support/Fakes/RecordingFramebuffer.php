<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\Region;

/** Wraps any Framebuffer and records which write verbs were called, in order. */
final class RecordingFramebuffer implements Framebuffer
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(public Framebuffer $inner) {}

    public function viewportWidth(): int { return $this->inner->viewportWidth(); }
    public function viewportHeight(): int { return $this->inner->viewportHeight(); }
    public function hostFormat(): FormatSpec { return $this->inner->hostFormat(); }
    public function getPixel(int $x, int $y): int { return $this->inner->getPixel($x, $y); }
    public function setPixel(int $x, int $y, int $value): static { $this->calls[] = 'setPixel'; $this->inner->setPixel($x, $y, $value); return $this; }
    public function setPixels(array $pixels): static { $this->calls[] = 'setPixels:'.count($pixels); $this->inner->setPixels($pixels); return $this; }
    public function setRegion(array $coordinates, int $value): static { $this->calls[] = 'setRegion'; $this->inner->setRegion($coordinates, $value); return $this; }
    public function setSegment(int $x, int $y, int $width, int $height, int $color): static { $this->calls[] = "setSegment:{$x},{$y},{$width},{$height}"; $this->inner->setSegment($x, $y, $width, $height, $color); return $this; }
    public function clear(): static { $this->calls[] = 'clear'; $this->inner->clear(); return $this; }
    public function fill(int $color): static { $this->calls[] = 'fill'; $this->inner->fill($color); return $this; }
    public function blitTo(Framebuffer $target, int $offset_x = 0, int $offset_y = 0): Framebuffer { return $this->inner->blitTo($target, $offset_x, $offset_y); }
    public function blitFrom(Framebuffer $source, int $offset_x = 0, int $offset_y = 0): Framebuffer { $this->calls[] = 'blitFrom'; $this->inner->blitFrom($source, $offset_x, $offset_y); return $this; }
    public function dump(?int $layer = null): string { return $this->inner->dump($layer); }
    public function flush(FormatSpec $spec, bool $as_array = false): string|array { return $this->inner->flush($spec, $as_array); }
    public function flushRegion(Region $region, FormatSpec $spec, bool $as_array = false): string|array { return $this->inner->flushRegion($region, $spec, $as_array); }
    public function toRgba8(): string { return $this->inner->toRgba8(); }
    public function damageGranularity(): DamageGranularity { return $this->inner->damageGranularity(); }
    public function preservesContentsOnPresent(): bool { return $this->inner->preservesContentsOnPresent(); }
    public function pointer(): int { return $this->inner->pointer(); }
}

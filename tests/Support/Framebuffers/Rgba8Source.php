<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\Framebuffers;

use LogicException;
use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\Region;

/** RGBA8 bytes as a read-only framebuffer: what a fixture's rgba8 op blits from. Pixel words are 0xRRGGBBAA. */
final class Rgba8Source implements Framebuffer
{
    public function __construct(private string $rgba8, private int $width, private int $height) {}

    public function viewportWidth(): int { return $this->width; }

    public function viewportHeight(): int { return $this->height; }

    public function hostFormat(): FormatSpec { return FormatSpec::rgba8(); }

    public function getPixel(int $x, int $y): int
    {
        return unpack('N', $this->rgba8, ($y * $this->width + $x) * 4)[1];
    }

    public function toRgba8(): string { return $this->rgba8; }

    public function dump(?int $layer = null): string { return $this->rgba8; }

    public function flush(FormatSpec $spec, bool $as_array = false): string|array { throw new LogicException('a source is only blitted from'); }

    public function flushRegion(Region $region, FormatSpec $spec, bool $as_array = false): string|array { throw new LogicException('a source is only blitted from'); }

    public function damageGranularity(): DamageGranularity { return DamageGranularity::pixel($this->width, $this->height); }

    public function preservesContentsOnPresent(): bool { return true; }

    public function pointer(): int { return 0; }

    public function blitTo(Framebuffer $target, int $offset_x = 0, int $offset_y = 0): Framebuffer { return $target->blitFrom($this, $offset_x, $offset_y); }

    public function setPixel(int $x, int $y, int $value): static { throw new LogicException('read-only source'); }

    public function setPixels(array $pixels): static { throw new LogicException('read-only source'); }

    public function setRegion(array $coordinates, int $value): static { throw new LogicException('read-only source'); }

    public function setSegment(int $x, int $y, int $width, int $height, int $color): static { throw new LogicException('read-only source'); }

    public function paintSpans(string $spans, int $rgba8): static { throw new LogicException('read-only source'); }

    public function clear(): static { throw new LogicException('read-only source'); }

    public function fill(int $color): static { throw new LogicException('read-only source'); }

    public function blitFrom(Framebuffer $source, int $offset_x = 0, int $offset_y = 0): Framebuffer { throw new LogicException('read-only source'); }
}

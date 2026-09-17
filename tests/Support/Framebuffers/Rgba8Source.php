<?php

namespace Venusian\Surface\Tests\Support\Framebuffers;

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;

/** RGBA8 bytes as a B32 ROW_MAJOR framebuffer, read-only. Pixel words are 0xRRGGBBAA. */
final class Rgba8Source implements Framebuffer
{
    private FormatSpec $format;

    public function __construct(private string $rgba8, private int $width, private int $height)
    {
        $this->format = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32);
    }

    public function viewportWidth(): int { return $this->width; }
    public function viewportHeight(): int { return $this->height; }
    public function hostFormat(): FormatSpec { return $this->format; }

    public function getPixel(int $x, int $y): int
    {
        $o = ($y * $this->width + $x) * 4;

        return (ord($this->rgba8[$o]) << 24) | (ord($this->rgba8[$o + 1]) << 16) | (ord($this->rgba8[$o + 2]) << 8) | ord($this->rgba8[$o + 3]);
    }

    public function toRgba8(): string { return $this->rgba8; }
    public function flush(FormatSpec $spec, bool $as_array = false): string|array { return $as_array ? array_values(unpack('C*', $this->rgba8)) : $this->rgba8; }
    public function flushRegion(Region $region, FormatSpec $spec, bool $as_array = false): string|array { throw new \LogicException('read-only source'); }
    public function dump(?int $layer = null): string { return $this->rgba8; }
    public function damageGranularity(): DamageGranularity { return DamageGranularity::pixel($this->width, $this->height); }
    public function preservesContentsOnPresent(): bool { return true; }
    public function pointer(): int { return 0; }
    public function blitTo(Framebuffer $target, int $offset_x = 0, int $offset_y = 0): Framebuffer { return $target->blitFrom($this, $offset_x, $offset_y); }

    public function setPixel(int $x, int $y, int $value): static { throw new \LogicException('read-only source'); }
    public function setPixels(array $pixels): static { throw new \LogicException('read-only source'); }
    public function setRegion(array $coordinates, int $value): static { throw new \LogicException('read-only source'); }
    public function setSegment(int $x, int $y, int $width, int $height, int $color): static { throw new \LogicException('read-only source'); }
    public function clear(): static { throw new \LogicException('read-only source'); }
    public function fill(int $color): static { throw new \LogicException('read-only source'); }
    public function blitFrom(Framebuffer $source, int $offset_x = 0, int $offset_y = 0): Framebuffer { throw new \LogicException('read-only source'); }
}

<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\Framebuffers;

use LogicException;
use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\Region;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\GradientSpread;

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

    public function paintMasks(array $masks, int $rgba8, Region $clip): static { throw new LogicException('read-only source'); }

    public function spanSink(): ?object { return null; }

    public function kawaseDown(Framebuffer $source, Region $region): static { throw new LogicException('read-only source'); }

    public function kawaseUp(Framebuffer $source): static { throw new LogicException('read-only source'); }

    public function paintGlass(array $geometry, Framebuffer $blur, int $scale, ?Framebuffer $sharp, int $originX, int $originY, array $look, Region $clip): static { throw new LogicException('read-only source'); }

    public function paintedSpans(array $regions): static { throw new LogicException('read-only source'); }

    public function paintImage(Framebuffer $source, Affine $placement, int $opacity = 255, Filter $filter = Filter::NEAREST, ?Region $clip = null, int $tint = 0xFFFFFFFF, ?array $window = null, ?string $coverage = null): static { throw new LogicException('read-only source'); }

    public function paintGradient(string $spans, string $ramp, string $kind, GradientSpread $spread, array $inverse): static { throw new LogicException('read-only source'); }

    public function scratch(int $width, int $height): Framebuffer { throw new LogicException('read-only source'); }

    public function filter(\Surface\Contracts\Drawing\Effects\FilterChain $chain, ?Region $region = null, bool $premultiplied = false): static { throw new LogicException('read-only source'); }

    public function clear(): static { throw new LogicException('read-only source'); }

    public function fill(int $color): static { throw new LogicException('read-only source'); }

    public function blitFrom(Framebuffer $source, int $offset_x = 0, int $offset_y = 0): Framebuffer { throw new LogicException('read-only source'); }

    public function writeRgba8(string $rgba8, int $width, int $height, int $x = 0, int $y = 0): static { throw new LogicException('read-only source'); }

    public function scroll(Region $region, int $dx, int $dy): void { throw new LogicException('read-only source'); }
}

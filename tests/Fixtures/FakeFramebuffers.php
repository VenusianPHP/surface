<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Fixtures;

use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelStore;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\Spans;
use Surface\Framebuffers\DirtyFramebuffer;
use Surface\Framebuffers\ePaperFramebuffer;
use Surface\Framebuffers\FullFramebuffer;
use Surface\Framebuffers\PagedFramebuffer;
use Surface\Framebuffers\RingFramebuffer;
use Surface\Framebuffers\StoreFramebuffer;

/**
 * A pixel store with no layout: one word per pixel in a PHP array, one byte
 * per pixel on the way out, whatever the format says. It lets the kinds be
 * tested for what they add (damage, pages, frames, paper) with no real store.
 */
final class FakePixelStore implements PixelStore
{
    /** @var list<int> */
    public array $words;

    public function __construct(private readonly FormatSpec $format, private readonly int $width, private readonly int $height)
    {
        $this->words = array_fill(0, $width * $height, 0);
    }

    public function width(): int { return $this->width; }

    public function height(): int { return $this->height; }

    public function format(): FormatSpec { return $this->format; }

    public function get(int $x, int $y): int { return $this->words[$y * $this->width + $x]; }

    public function set(int $x, int $y, int $value): void { $this->words[$y * $this->width + $x] = $value & 0xFF; }

    public function setPixels(array $pixels): ?Region
    {
        foreach ($pixels as [$x, $y, $value]) {
            $this->set($x, $y, $value);
        }

        return $this->box($pixels);
    }

    public function setCoordinates(array $coordinates, int $value): ?Region
    {
        foreach ($coordinates as [$x, $y]) {
            $this->set($x, $y, $value);
        }

        return $this->box($coordinates);
    }

    public function rect(Region $region, int $value): void
    {
        for ($y = $region->y; $y < $region->bottom(); $y++) {
            for ($x = $region->x; $x < $region->right(); $x++) {
                $this->set($x, $y, $value);
            }
        }
    }

    public function fill(int $value): void { $this->words = array_fill(0, $this->width * $this->height, $value & 0xFF); }

    public function bytes(?int $layer = null): string { return is_null($layer) ? pack('C*', ...$this->words) : "layer {$layer}"; }

    public function region(Region $region, FormatSpec $spec): string
    {
        $out = '';
        for ($y = $region->y; $y < $region->bottom(); $y++) {
            for ($x = $region->x; $x < $region->right(); $x++) {
                $out .= chr($this->get($x, $y));
            }
        }

        return $out;
    }

    public function toRgba8(): string
    {
        return implode('', array_map(fn (int $word): string => str_repeat(chr($word), 3)."\xff", $this->words));
    }

    /** The red channel is the word. */
    public function blitRgba8(string $rgba8, int $width, int $height, int $offset_x, int $offset_y, ?Region $clip = null): ?Region
    {
        $target = (new Region($offset_x, $offset_y, $width, $height))->intersect($clip ?? Region::wholeSurface($this->width, $this->height))
            ?->intersect(Region::wholeSurface($this->width, $this->height));
        if (is_null($target)) {
            return null;
        }
        for ($y = $target->y; $y < $target->bottom(); $y++) {
            for ($x = $target->x; $x < $target->right(); $x++) {
                $this->set($x, $y, ord($rgba8[(($y - $offset_y) * $width + $x - $offset_x) * 4]));
            }
        }

        return $target;
    }

    /** Writes the colour's low byte wherever coverage is non-zero: enough to see where a kind routes spans. */
    public function paintSpans(string $spans, int $rgba8): ?Region
    {
        $box = null;
        foreach (Spans::unpack($spans) as [$y, $x, $length, $coverage]) {
            for ($i = $x; $i < $x + $length; $i++) {
                if ($coverage > 0) {
                    $this->set($i, $y, $rgba8);
                }
            }
            $span = new Region($x, $y, $length, 1);
            $box = is_null($box) ? $span : $box->union($span);
        }

        return $box;
    }

    /** Writes the red byte of the nearest source pixel wherever the point lands inside: enough to see where a kind routes an image. */
    public function paintRgba8(string $rgba8, int $width, int $height, array $inverse, Region $target, int $opacity, Filter $filter, int $row = 0): void
    {
        [$a, $b, $c, $d, $e, $f] = $inverse;
        for ($y = $target->y; $y < $target->bottom(); $y++) {
            for ($x = $target->x; $x < $target->right(); $x++) {
                $u = $a * ($x + 0.5) + $c * ($y + $row + 0.5) + $e;
                $v = $b * ($x + 0.5) + $d * ($y + $row + 0.5) + $f;
                if ($u >= 0 && $u < $width && $v >= 0 && $v < $height) {
                    $this->set($x, $y, ord($rgba8[((int) $v * $width + (int) $u) * 4]));
                }
            }
        }
    }

    public function copy(PixelStore $source, Region $region): void
    {
        for ($y = $region->y; $y < $region->bottom(); $y++) {
            for ($x = $region->x; $x < $region->right(); $x++) {
                $this->set($x, $y, $source->get($x, $y));
            }
        }
    }

    public function plane(int $value): string { return "plane {$value}"; }

    public function pointer(): int { return 0; }

    public function granularity(): DamageGranularity { return DamageGranularity::pixel($this->width, $this->height); }

    private function box(array $points): ?Region
    {
        if ($points === []) {
            return null;
        }
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return new Region(min($xs), min($ys), max($xs) - min($xs) + 1, max($ys) - min($ys) + 1);
    }
}

trait MintsFakeStores
{
    protected function mint(FormatSpec $format, int $width, int $height): PixelStore
    {
        return new FakePixelStore($format, $width, $height);
    }
}

final class FakeFullFramebuffer extends FullFramebuffer
{
    use MintsFakeStores;
}

final class FakeDirtyFramebuffer extends DirtyFramebuffer
{
    use MintsFakeStores;
}

final class FakeePaperFramebuffer extends ePaperFramebuffer
{
    use MintsFakeStores;
}

final class FakePagedFramebuffer extends PagedFramebuffer
{
    protected function window(FormatSpec $format, int $width, int $rows): StoreFramebuffer
    {
        return new FakeFullFramebuffer($format, $width, $rows);
    }
}

final class FakeRingFramebuffer extends RingFramebuffer
{
    protected function slot(FormatSpec $format, int $width, int $height): DirtyFramebuffer
    {
        return new FakeDirtyFramebuffer($format, $width, $height);
    }
}

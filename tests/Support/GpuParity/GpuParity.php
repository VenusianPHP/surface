<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\GpuParity;

use Closure;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Drawing\Gpu\GpuRenderingEngine;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Fonts\ClassicFont;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\NutsAndBolts\Color;
use Surface\Rasterize\Native\NativeRasterizeDriver;

/**
 * What every GPU engine is held to, run by each engine package against its
 * real device: register() in a test file of the package, under Surface's Pest.
 *
 *   1. The scene set, drawn as Velvet draws it. Clears, whole-pixel rects,
 *      whole-pixel text and nearest images at whole-pixel placement: every
 *      pixel. Every other shape: every pixel more than one pixel from an
 *      edge, which is a pixel whose eight neighbours in Velvet's frame equal it.
 *   2. Partial frames byte for byte as the same engine's whole redraws.
 *   3. flushRegion() into RGBA8, RGB565 and 1-bit as the native packings pack
 *      the engine's own RGBA8.
 */
final class GpuParity
{
    public const int WIDTH = 64;

    public const int HEIGHT = 32;

    /**
     * Define the suite's tests for one engine.
     *
     * @param  string  $name  Leads each test's description.
     * @param  Closure(int, int, Edges): RenderingEngine  $make  A new engine of that size and edge mode, offscreen, each call.
     */
    public static function register(string $name, Closure $make): void
    {
        \it("{$name}: draws the scene as Velvet does", function (string $scene, string $edges) use ($make): void {
            [$exact, $draw] = GpuParity::scenes()[$scene];
            $subject = $make(GpuParity::WIDTH, GpuParity::HEIGHT, Edges::from($edges));
            $velvet = GpuParity::velvet(GpuParity::WIDTH, GpuParity::HEIGHT, Edges::from($edges));

            try {
                $subject->frame($draw);
                $velvet->frame($draw);

                \expect(GpuParity::mismatches($subject->framebuffer()->toRgba8(), $velvet->framebuffer()->toRgba8(), GpuParity::WIDTH, GpuParity::HEIGHT, $exact))->toBe([]);
            } finally {
                GpuParity::release($subject);
            }
        })->with(array_keys(self::scenes()))->with(['antialiased', 'hard']);

        \it("{$name}: draws partial frames byte for byte as its own whole redraws", function () use ($make): void {
            $partial = $make(GpuParity::WIDTH, GpuParity::HEIGHT, Edges::ANTIALIASED);
            $whole = $make(GpuParity::WIDTH, GpuParity::HEIGHT, Edges::ANTIALIASED);
            $tile = GpuParity::tile();

            try {
                foreach (range(0, 4) as $i) {
                    $partial->frame(fn (RenderingEngine $g) => GpuParity::clockFrame($g, $i, $tile));
                    $whole->invalidate()->frame(fn (RenderingEngine $g) => GpuParity::clockFrame($g, $i, $tile));

                    \expect($partial->framebuffer()->toRgba8())->toBe($whole->framebuffer()->toRgba8(), "frame {$i}");
                }
            } finally {
                GpuParity::release($partial);
                GpuParity::release($whole);
            }
        });

        \it("{$name}: flushes a region as the native packings pack its RGBA8", function (string $format, array $box) use ($make): void {
            $spec = GpuParity::formats()[$format];
            $region = new Region(...$box);
            $subject = $make(GpuParity::WIDTH, GpuParity::HEIGHT, Edges::ANTIALIASED);

            try {
                $subject->frame(GpuParity::scenes()['every shape'][1]);
                $reference = new NativeFullFramebuffer(FormatSpec::rgba8(), GpuParity::WIDTH, GpuParity::HEIGHT);
                $reference->writeRgba8($subject->framebuffer()->toRgba8(), GpuParity::WIDTH, GpuParity::HEIGHT);

                \expect($subject->framebuffer()->flushRegion($region, $spec))->toBe($reference->flushRegion($region, $spec));
            } finally {
                GpuParity::release($subject);
            }
        })->with(array_keys(self::formats()))->with([
            'the whole target' => [[0, 0, self::WIDTH, self::HEIGHT]],
            'a region' => [[8, 4, 32, 16]],
        ]);
    }

    /**
     * The scene set: name => [exact on every pixel, the frame].
     *
     * @return array<string, array{bool, Closure(RenderingEngine): void}>
     */
    public static function scenes(): array
    {
        return [
            'a clear' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::hex('#101820'));
            }],
            'rects on whole pixels' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::hex('#101820'));
                $g->fillRect(2, 3, 10, 6, Color::hex('#ff6600'));
                $g->fillRect(8, 6, 30, 12, Color::rgba(40, 200, 255, 0.5));
                $g->clip(new Region(40, 0, 10, 10));
                $g->fillRect(36, 4, 20, 20, Color::rgb(255, 255, 255));
            }],
            'text on whole pixels' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(0, 0, 0));
                $g->text('12:34', 4, 2, Color::rgb(255, 255, 255), new ClassicFont);
                $g->push()->scale(2)->text('Ab', 2, 7, Color::rgb(255, 255, 0), new ClassicFont)->pop();
            }],
            'nearest images on whole pixels' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(0, 0, 0));
                $g->image(GpuParity::tile(), 4, 4);
                $g->image(GpuParity::tile(), 20, 2, 24, 24);
                $g->image(GpuParity::tile(), 50, 10, opacity: 0.5);
            }],
            'every shape' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::hex('#101820'));
                $g->fillRect(2.5, 3, 10, 6, Color::hex('#ff6600'));
                $g->fillEllipse(20, 12, 7, 5, Color::rgba(40, 200, 255, 0.5));
                $g->line(1, 22, 30, 2, Color::rgb(255, 255, 255));
                $g->polyline([[3, 3], [28, 5], [16, 20]], Color::rgb(0, 200, 0), 2.5, true);
                $g->strokeEllipse(46, 16, 12, 9, Color::rgb(60, 60, 255), 4);
                $g->fillTriangle(34, 1, 62, 2, 40, 30, Color::rgba(255, 0, 255, 0.7));
                $g->fillPolygon([[20, 14], [30, 22], [30, 14], [20, 22]], Color::rgb(255, 255, 0), FillRule::EVEN_ODD);
            }],
            'shapes under a clip and a turn' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(0, 0, 0));
                $g->clip(new Region(6, 4, 50, 24));
                $g->push()->translate(32, 16)->rotate(0.5);
                $g->fillRect(-20, -8, 40, 16, Color::rgb(200, 40, 40));
                $g->fillEllipse(0, 0, 18, 6, Color::rgba(255, 255, 255, 0.6));
                $g->text('Ab', -6, -4, Color::rgb(0, 0, 0), new ClassicFont);
                $g->pop();
                $g->fillPath([[[8, 6], [30, 6], [30, 26], [8, 26]], [[12, 10], [12, 22], [26, 22], [26, 10]]], Color::rgb(0, 160, 255));
            }],
            'images scaled and turned' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::hex('#202020'));
                $g->image(GpuParity::tile(), 2.5, 3.5, 20, 12, filter: Filter::LINEAR);
                $g->push()->translate(44, 16)->rotate(0.4)->image(GpuParity::tile(), -8, -8, 16, 16)->pop();
            }],
        ];
    }

    /** @return array<string, FormatSpec> The formats flushRegion() is held to. */
    public static function formats(): array
    {
        return [
            'RGBA8' => FormatSpec::rgba8(),
            'RGB565' => new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16, endianness: Endianness::MSB),
            '1-bit' => new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::MSB_FIRST),
        ];
    }

    /** The reference: Velvet on a native RGBA8 framebuffer, native geometry. */
    public static function velvet(int $width, int $height, Edges $edges): VelvetGE
    {
        return new VelvetGE(new NativeFullFramebuffer(FormatSpec::rgba8(), $width, $height), new NativeRasterizeDriver(), $edges);
    }

    /** An 8 x 8 image with no symmetry: a red-to-green ramp across, blue growing down, one white corner. */
    public static function tile(): Framebuffer
    {
        $tile = new NativeFullFramebuffer(FormatSpec::rgba8(), 8, 8);
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $tile->setPixel($x, $y, ((255 - $x * 32) << 24) | (($x * 32) << 16) | (($y * 32) << 8) | 0xFF);
            }
        }
        $tile->setPixel(0, 0, 0xFFFFFFFF);

        return $tile;
    }

    /** Frame $i of a clock: a border that stays, a dot that moves, digits that change, a line that grows, a fixed image, and a triangle on frame 3 only. */
    public static function clockFrame(RenderingEngine $g, int $i, Framebuffer $tile): void
    {
        $g->clear(Color::rgb(0, 0, 0));
        $g->strokeRect(1, 1, 62, 30, Color::rgb(255, 255, 255));
        $g->fillEllipse(10 + 3 * $i, 16, 4, 3, Color::rgba(255, 128, 0, 0.8));
        $g->text(sprintf('%02d', 10 + $i), 30, 4, Color::rgb(255, 255, 255), new ClassicFont);
        $g->line(2, 28, 20 + 5 * $i, 20, Color::rgb(0, 255, 0), 1.5);
        $g->image($tile, 44, 18);
        if ($i === 3) {
            $g->push()->translate(0.5, 0.5)->fillTriangle(40, 2, 46, 10, 36, 9, Color::rgb(0, 0, 255))->pop();
        }
    }

    /**
     * Where two RGBA8 frames break the parity rule.
     *
     * @param  bool  $exact  Every pixel must match; otherwise only a pixel whose neighbours in $reference equal it.
     * @return list<string> One line a pixel; [] when the frames agree.
     */
    public static function mismatches(string $subject, string $reference, int $width, int $height, bool $exact): array
    {
        if (strlen($subject) !== strlen($reference)) {
            return ['The frames differ in size: '.strlen($subject).' bytes, Velvet '.strlen($reference).'.'];
        }

        $out = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $at = ($y * $width + $x) * 4;
                $want = substr($reference, $at, 4);
                $got = substr($subject, $at, 4);
                if ($got === $want || (! $exact && ! self::flat($reference, $width, $height, $x, $y))) {
                    continue;
                }
                $out[] = sprintf('(%d, %d): %s, Velvet %s', $x, $y, bin2hex($got), bin2hex($want));
            }
        }

        return $out;
    }

    /** Let go of an engine's device, where it has one. */
    public static function release(RenderingEngine $engine): void
    {
        if ($engine instanceof GpuRenderingEngine) {
            $engine->release();
        }
    }

    /** Whether every neighbour of ($x, $y) inside the frame equals it. */
    private static function flat(string $rgba8, int $width, int $height, int $x, int $y): bool
    {
        $pixel = substr($rgba8, ($y * $width + $x) * 4, 4);
        for ($ny = max(0, $y - 1); $ny <= min($height - 1, $y + 1); $ny++) {
            for ($nx = max(0, $x - 1); $nx <= min($width - 1, $x + 1); $nx++) {
                if (substr($rgba8, ($ny * $width + $nx) * 4, 4) !== $pixel) {
                    return false;
                }
            }
        }

        return true;
    }
}

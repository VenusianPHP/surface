<?php

declare(strict_types=1);

namespace Venusian\Surface\Tests\Support\GpuParity;

use Closure;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\Endianness;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Drawing\Effects\Blend;
use Surface\Contracts\Drawing\Effects\BlendMode;
use Surface\Contracts\Drawing\Effects\Blur;
use Surface\Contracts\Drawing\Effects\ColorMatrix;
use Surface\Contracts\Drawing\Effects\Composite;
use Surface\Contracts\Drawing\Effects\CompositeOperator;
use Surface\Contracts\Drawing\Effects\Displace;
use Surface\Contracts\Drawing\Effects\EdgeMode;
use Surface\Contracts\Drawing\Effects\Field;
use Surface\Contracts\Drawing\Effects\Table;
use Surface\Contracts\Drawing\Effects\FilterChain;
use Surface\Contracts\Drawing\Effects\Flood;
use Surface\Contracts\Drawing\Effects\Noise;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Fonts\TrueTypeFont;
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
use Venusian\Surface\Tests\Support\Fakes\FakeFontDriver;

// Engine packages load this file by path, outside Surface's test autoloader.
require_once __DIR__.'/../Fakes/FakeFontDriver.php';

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
            [$exact, $draw, $tolerance] = GpuParity::scenes()[$scene] + [2 => 0];
            $subject = $make(GpuParity::WIDTH, GpuParity::HEIGHT, Edges::from($edges));
            $velvet = GpuParity::velvet(GpuParity::WIDTH, GpuParity::HEIGHT, Edges::from($edges));

            try {
                $subject->frame($draw);
                $velvet->frame($draw);

                \expect(GpuParity::mismatches($subject->framebuffer()->toRgba8(), $velvet->framebuffer()->toRgba8(), GpuParity::WIDTH, GpuParity::HEIGHT, $exact, $tolerance))->toBe([]);
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

    /** A round-capped stroke from (x1, y1) to (x2, y2), $width across, as one contour: two half circles of 8 steps. */
    public static function capsule(float $x1, float $y1, float $x2, float $y2, float $width): array
    {
        $r = $width / 2;
        $a = atan2($x2 - $x1, -($y2 - $y1));
        $points = [];
        foreach ([[$x2, $y2, $a], [$x1, $y1, $a + M_PI]] as [$cx, $cy, $start]) {
            for ($i = 0; $i <= 8; $i++) {
                $t = $start - M_PI * $i / 8;
                $points[] = [$cx + $r * cos($t), $cy + $r * sin($t)];
            }
        }

        return $points;
    }

    /**
     * The scene set: name => [exact on every pixel, the frame, and optionally a tolerance per channel at every pixel].
     *
     * @return array<string, array{0: bool, 1: Closure(RenderingEngine): void, 2?: int}>
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
            'truetype text' => [true, function (RenderingEngine $g): void {
                // FakeFontDriver's masks are fixed, so every engine draws the same glyphs; the translate makes
                // the glyph origins round.
                $font = new TrueTypeFont('sans-serif', 9.0, new FakeFontDriver);
                $g->clear(Color::rgb(0, 0, 0));
                $g->push()->translate(0.4, 0.6);
                $g->text("Hi\nAV", 2, 2, Color::rgb(255, 255, 255), $font);
                $g->text('AV', 30, 4, Color::rgba(40, 200, 255, 0.5), $font);
                $g->pop();
            }],
            'rounded rects' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::hex('#101820'));
                $g->roundRect(2, 2, 24, 12, [4, 4, 4, 4], Color::hex('#ff6600'));
                $g->roundRect(28, 3, 30, 14, [6, 0, 3, 9], Color::rgba(40, 200, 255, 0.6), Color::rgb(255, 255, 255), 2.0);
                $g->roundRect(4, 18, 20, 10, [5, 5, 5, 5], Color::rgb(0, 200, 0), Color::rgba(255, 0, 255, 0.7), 6.0);
                $g->clip(new Region(30, 20, 20, 8));
                $g->roundRect(26, 16, 30, 16, [8, 8, 8, 8], Color::rgb(255, 255, 0));
            }],
            // Tolerant by 1: float32 on a GPU and double on the CPU can round a coverage step apart.
            'shadows' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(240, 240, 240));
                $g->shadow(6, 4, 20, 14, 4, 6, Color::rgba(0, 0, 0, 0.6));
                $g->shadow(38, 6, 16, 10, 0, 3, Color::rgb(40, 80, 200));
                $g->shadow(30, 22, 12, 6, 2, 0, Color::rgba(200, 0, 0, 0.5));
            }, 1],
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
            // round-capped strokes at sub-pixel widths, as a module lowers a line to a path (Silk: Raven's
            // max(1, thickness x scale), unrounded); straight and diagonal
            'sub-pixel capsules' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(0, 0, 0));
                foreach ([1.0, 1.25, 1.5, 2.5] as $i => $width) {
                    $x = 3 + 15 * $i;
                    $g->fillPath([GpuParity::capsule($x, 4.3, $x + 10, 4.3, $width)], Color::rgb(255, 255, 255));
                    $g->fillPath([GpuParity::capsule($x + 0.4, 9, $x + 0.4, 18, $width)], Color::rgba(120, 220, 255, 0.8));
                    $g->fillPath([GpuParity::capsule($x, 21, $x + 11, 29.5, $width)], Color::rgb(255, 180, 40));
                }
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
            // glyphs under a scale whose x and y differ (a module's chip stretched by its speed: Silk, Raven
            // dock_area.c:1416-1442); every engine applies the affine to text as Velvet does
            'glyphs scaled apart' => [false, function (RenderingEngine $g): void {
                $font = new TrueTypeFont('sans-serif', 9.0, new FakeFontDriver);
                $g->clear(Color::rgb(0, 0, 0));
                $g->push()->translate(2, 2)->scale(2, 1)->text('Ab', 0, 0, Color::rgb(255, 255, 255), new ClassicFont)->pop();
                $g->push()->translate(2, 14)->scale(1, 1.5)->text('Ab', 0, 0, Color::rgb(255, 255, 0), new ClassicFont)->pop();
                $g->push()->translate(36.4, 3.6)->scale(1.6, 0.8)->text('AV', 0, 0, Color::rgb(120, 220, 255), $font)->pop();
                $g->push()->translate(36.4, 16.6)->scale(0.8, 1.4)->text('AV', 0, 0, Color::rgba(255, 180, 40, 0.8), $font)->pop();
            }],
            // The tint multiplies each pixel (alpha too), then the opacity, then the coverage: whole-pixel placement, nearest.
            'tinted images' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(230, 230, 240));
                $g->image(GpuParity::tile(), 4, 4, tint: Color::rgba(255, 128, 64, 0.75));
                $g->image(GpuParity::tile(), 20, 2, 24, 24, 0.5, tint: Color::rgb(0, 255, 255));
                $g->image(GpuParity::tile(), 50, 10, tint: Color::rgba(255, 255, 255, 0.4));
            }],
            // A source rect scales and mirrors onto the destination; the window crops, nearest, whole pixels.
            'image source rects' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(20, 20, 30));
                $g->image(GpuParity::tile(), 2, 2, 8, 8, sourceRect: [2, 2, 4, 4]);
                $g->image(GpuParity::tile(), 14, 2, 8, 8, sourceRect: [6, 0, -4, 4]);
                $g->image(GpuParity::tile(), 26, 2, sourceRect: [1, 1, 5, 6], tint: Color::rgba(255, 255, 0, 0.8));
                $g->image(GpuParity::tile(), 4, 16, 12, 12, 0.7, sourceRect: [0, 7, 8, -7]);
            }],
            // Rounded corners: the rounded box's coverage at the edge, so tolerant there like every rounded shape.
            'rounded images' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(240, 240, 240));
                $g->image(GpuParity::tile(), 4, 4, 24, 24, radii: [6, 10, 0, 3]);
                $g->image(GpuParity::tile(), 34, 4, 20, 16, 0.5, radii: [8, 8, 8, 8], tint: Color::rgb(255, 200, 200));
                $g->image(GpuParity::tile(), 36, 22, 24, 8, radii: [4, 4, 4, 4], sourceRect: [0, 2, 8, 4]);
            }],
            // Curves flatten in Surface and strokes become polygon outlines: every engine fills the same polygons.
            'curves and strokes' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(240, 240, 240));
                $g->fill((new \Surface\NutsAndBolts\Path())->moveTo(3, 28)->cubicTo(3, 2, 27, 2, 27, 28)->close(), Color::rgb(60, 120, 200));
                $g->stroke((new \Surface\NutsAndBolts\Path())->moveTo(32, 26)->cubicTo(36, 4, 48, 4, 52, 20)->quadTo(56, 28, 62, 12),
                    Color::rgb(200, 40, 40), new \Surface\Contracts\Drawing\StrokeStyle(width: 3.0, cap: \Surface\Contracts\Rasterize\StrokeCap::ROUND, join: \Surface\Contracts\Rasterize\StrokeJoin::ROUND));
                $g->stroke((new \Surface\NutsAndBolts\Path())->moveTo(32, 30)->lineTo(46, 30)->lineTo(46, 22)->lineTo(60, 22),
                    Color::rgb(20, 20, 20), new \Surface\Contracts\Drawing\StrokeStyle(width: 2.0, cap: \Surface\Contracts\Rasterize\StrokeCap::SQUARE, join: \Surface\Contracts\Rasterize\StrokeJoin::BEVEL, dash: [4.0, 2.0]));
            }],
            // A gradient's colour at a pixel is a ramp entry of its position: every engine reads the same 256 colours.
            'gradients' => [false, function (RenderingEngine $g): void {
                $heat = [[0.0, Color::rgb(255, 0, 0)], [0.5, Color::rgb(255, 255, 0)], [1.0, Color::rgb(0, 0, 255)]];
                $box = fn (float $x0, float $y0, float $x1, float $y1) => (new \Surface\NutsAndBolts\Path())->moveTo($x0, $y0)->lineTo($x1, $y0)->lineTo($x1, $y1)->lineTo($x0, $y1)->close();
                $g->clear(Color::rgb(230, 230, 230));
                $g->fill($box(2, 2, 30, 14), new \Surface\NutsAndBolts\LinearGradient(2, 0, 30, 0, $heat));
                $g->fill($box(34, 2, 62, 14), new \Surface\NutsAndBolts\RadialGradient(48, 8, 10, $heat, \Surface\NutsAndBolts\GradientSpread::REFLECT));
                $g->stroke((new \Surface\NutsAndBolts\Path())->moveTo(4, 24)->cubicTo(16, 14, 40, 34, 60, 22),
                    new \Surface\NutsAndBolts\LinearGradient(0, 0, 32, 0, $heat, \Surface\NutsAndBolts\GradientSpread::REPEAT),
                    new \Surface\Contracts\Drawing\StrokeStyle(width: 5.0, cap: \Surface\Contracts\Rasterize\StrokeCap::ROUND));
                $g->fill($box(40, 18, 62, 30), new \Surface\NutsAndBolts\LinearGradient(40, 0, 62, 0, [[0.0, Color::rgba(0, 0, 0, 0.0)], [1.0, Color::rgba(0, 0, 0, 0.8)]]));
            }],
            // A group drawn apart and laid down at its opacity: whole pixels, nested, no filter: every pixel is exact.
            'layers' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(235, 235, 245));
                $g->layer(4, 4, 24, 20, function (RenderingEngine $l): void {
                    $l->fillRect(0, 0, 18, 14, Color::rgb(220, 30, 30));
                    $l->fillRect(10, 8, 18, 14, Color::rgb(30, 30, 220));
                }, 0.5);
                $g->layer(32, 2, 28, 28, function (RenderingEngine $l): void {
                    $l->fillRect(0, 0, 40, 40, Color::rgba(0, 150, 0, 0.6));
                    $l->layer(40, 8, 12, 12, fn (RenderingEngine $m) => $m->fillRect(0, 0, 64, 32, Color::rgb(0, 0, 0)), 0.75);
                });
            }],
            // A rounded box: the rounded coverage at the edge, so tolerant there like every rounded shape.
            'rounded layers' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(235, 235, 245));
                $g->layer(4, 4, 28, 24, fn (RenderingEngine $l) => $l->fillRect(0, 0, 64, 32, Color::rgb(30, 150, 90)), 0.8, [10, 4, 10, 4]);
                $g->layer(36, 6, 24, 20, function (RenderingEngine $l): void {
                    $l->fillRect(0, 0, 64, 16, Color::rgb(200, 120, 20));
                    $l->fillRect(0, 16, 64, 16, Color::rgb(20, 120, 200));
                }, 1.0, [8, 8, 8, 8]);
            }],
            // Box blurs and kernels are integer arithmetic: exact. A colour matrix and a composite are float: one level.
            'layer filters' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(250, 250, 250));
                $g->layer(2, 2, 28, 28, fn (RenderingEngine $l) => $l->fillRect(6, 6, 16, 16, Color::rgb(220, 40, 40)), 1.0, null,
                    FilterChain::of(Blur::gaussian(2.5, 2.5)));
                $g->layer(34, 2, 28, 28, fn (RenderingEngine $l) => $l->fillRect(6, 6, 16, 16, Color::rgb(40, 40, 220)), 1.0, null,
                    FilterChain::of(Blur::gaussian(1.2, 0.6, EdgeMode::DUPLICATE)));
            }],
            'layer filters in float' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(250, 250, 250));
                $g->layer(2, 2, 28, 28, fn (RenderingEngine $l) => $l->fillRect(4, 4, 20, 20, Color::rgb(220, 40, 40)), 1.0, null,
                    FilterChain::of(ColorMatrix::saturate(0.4), ColorMatrix::hueRotate(60.0)));
                $g->layer(34, 2, 28, 28, fn (RenderingEngine $l) => $l->fillRect(4, 4, 20, 20, Color::rgb(40, 40, 220)), 0.9, null,
                    FilterChain::of(new Flood(Color::rgba(200, 40, 90, 0.6), 'f'), new Composite('source', CompositeOperator::ATOP, in: 'f'),
                        new Flood(Color::rgb(30, 200, 90), 'g'), new Blend('g', BlendMode::MULTIPLY)));
            }],
            // A backdrop's blur reads what was drawn under the box: the box's own pixels are the only ones that change.
            'backdrop blur' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(30, 30, 40));
                $g->fillRect(0, 0, 32, 32, Color::rgb(230, 60, 60));
                $g->fillRect(32, 0, 32, 32, Color::rgb(60, 60, 230));
                $g->fillRect(24, 4, 16, 4, Color::rgb(240, 240, 80));
                $g->layer(12, 8, 40, 18, fn (RenderingEngine $l) => $l->fillRect(0, 0, 64, 32, Color::rgba(255, 255, 255, 0.15)), 1.0, null, null,
                    FilterChain::of(Blur::gaussian(3.0, 3.0, EdgeMode::DUPLICATE)));
            }],
            'backdrop spiral blur' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(30, 30, 40));
                $g->fillRect(0, 0, 32, 32, Color::rgb(230, 60, 60));
                $g->fillRect(32, 0, 32, 32, Color::rgb(60, 60, 230));
                $g->layer(12, 8, 40, 18, fn () => null, 1.0, null, null, FilterChain::of(Blur::spiral(5.0, 16, EdgeMode::DUPLICATE)));
            }],
            // Hash noise is integer arithmetic of the surface position, and turbulence, spiral reads and displacement are worked in float32 to the same rounding: every engine measured equal to Velvet, so no tolerance.
            'layer noise' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(200, 200, 210));
                $g->layer(2, 2, 28, 28, fn () => null, 0.5, null, FilterChain::of(Noise::hash(7)));
                $g->layer(34, 2, 28, 28, fn () => null, 0.8, null, FilterChain::of(Noise::turbulence(0.09, 0.09, 3, 11)));
            }],
            // A displacement map moves the pixels; with bands each channel samples along the displacement (dispersion).
            'layer displacement' => [true, function (RenderingEngine $g): void {
                $map = GpuParity::map();
                $g->clear(Color::rgb(245, 245, 245));
                $g->layer(2, 2, 28, 28, function (RenderingEngine $l): void {
                    $l->fillRect(4, 4, 8, 20, Color::rgb(200, 30, 30));
                    $l->fillRect(14, 4, 8, 20, Color::rgb(30, 30, 200));
                }, 1.0, null, FilterChain::of(new Displace($map, 6.0)));
                $g->layer(34, 2, 28, 28, fn (RenderingEngine $l) => $l->fillRect(6, 6, 16, 16, Color::rgb(30, 30, 30)), 1.0, null,
                    FilterChain::of(new Displace($map, 8.0, bands: 7, spread: 0.4)));
            }],
            // Images made from the pixel position: a rounded box's distance and direction, its inside, a ramp. The distance is worked in float32 on a GPU and in double on the CPU, so a byte may differ by one.
            'layer fields' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(90, 90, 100));
                $g->layer(2, 2, 28, 28, fn () => null, 1.0, null, FilterChain::of(Field::roundedBox([4.3, 5.2, 27.6, 26.1], [6.0, 3.0, 8.5, 1.0], -6.0, 0.0)));
                $g->layer(34, 2, 28, 12, fn () => null, 1.0, null, FilterChain::of(Field::inside([36.0, 3.0, 61.0, 13.0], [5.0, 5.0, 5.0, 5.0])));
                $g->layer(34, 16, 28, 14, fn () => null, 0.8, null, FilterChain::of(Field::ramp(16.0, 30.0)));
            }, 1],
            // A table is a lookup of the straight colour: exact on every engine.
            'layer tables' => [true, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(200, 200, 210));
                $invert = array_map(fn (int $i): int => 255 - $i, range(0, 255));
                $half = array_map(fn (int $i): int => intdiv($i, 2) + 40, range(0, 255));
                $g->layer(2, 2, 28, 28, fn (RenderingEngine $l) => $l->fillRect(4, 4, 20, 20, Color::rgba(220, 40, 40, 0.7)), 1.0, null, FilterChain::of(new Table($invert, null, $half, $half)));
                $g->layer(34, 2, 28, 28, fn () => null, 1.0, null, FilterChain::of(Field::ramp(2.0, 30.0), Table::rgb(fn (float $v): float => $v ** 3)));
            }],
            // A displacement whose map is made in the chain (the last colour matrix is float, so the map is off by one in places, which moves a sharp edge by a little: only the pixels away from an edge are held): a field, a table for the bend and a matrix for the shift, as a glass pane's rim.
            'layer displacement by a made map' => [false, function (RenderingEngine $g): void {
                $g->clear(Color::rgb(245, 245, 245));
                $g->layer(2, 2, 28, 28, function (RenderingEngine $l): void {
                    $l->fillRect(4, 4, 8, 20, Color::rgb(200, 30, 30));
                    $l->fillRect(14, 4, 8, 20, Color::rgb(30, 30, 200));
                }, 1.0, null, FilterChain::of(
                    Field::roundedBox([3.0, 3.0, 29.0, 29.0], [6.0, 6.0, 6.0, 6.0], -8.0, 0.0, 'f'),
                    Field::inside([3.0, 3.0, 29.0, 29.0], [6.0, 6.0, 6.0, 6.0], 'in'),
                    new ColorMatrix([0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1], 'f', 'x'),
                    new ColorMatrix([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 1], 'f', 't'),
                    Table::rgb(fn (float $v): float => $v ** 3, 't', 'b'),
                    Composite::arithmetic('in', 1.0, 0.0, 0.0, 0.0, in: 'b', result: 'p'),
                    Composite::arithmetic('p', 1.0, 0.0, 0.0, 0.0, in: 'x', result: 'q'),
                    new ColorMatrix([-1, 0, 0.5, 0, 0.5, 0, -1, 0.5, 0, 0.5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1], 'q', 'm'),
                    new Displace('m', 8.0, in: 'source'),
                ));
            }, 4],
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
    /** A 64 x 32 displacement map: red climbs along x, green along y, blue holds mid-grey. */
    public static function map(): Framebuffer
    {
        $bytes = '';
        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $bytes .= chr(($x * 4) % 256).chr(($y * 8) % 256)."\x80\xff";
            }
        }

        return (new NativeFullFramebuffer(FormatSpec::rgba8(), self::WIDTH, self::HEIGHT))->writeRgba8($bytes, self::WIDTH, self::HEIGHT);
    }

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
    public static function mismatches(string $subject, string $reference, int $width, int $height, bool $exact, int $tolerance = 0): array
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
                if ($got === $want || ($tolerance > 0 && self::near($got, $want, $tolerance)) || (! $exact && ! self::flat($reference, $width, $height, $x, $y))) {
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

    /** Whether no channel of two pixels differs by more than $tolerance. */
    private static function near(string $a, string $b, int $tolerance): bool
    {
        for ($i = 0; $i < 4; $i++) {
            if (abs(ord($a[$i]) - ord($b[$i])) > $tolerance) {
                return false;
            }
        }

        return true;
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

<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Fonts\TrueTypeFont;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\Spans;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Fakes\FakeFontDriver;

/*
 * Velvet paints a 'glyphs' command as coverage spans. FakeFontDriver at size 8: a 4x6 mask, 255
 * inside and 128 on its ring; text('A', 1, 1) puts its top-left at (1, 1).
 */

const GLYPH_INK = 0xFFCC00FF;

function glyphFont(): TrueTypeFont
{
    return new TrueTypeFont('sans-serif', 8.0, new FakeFontDriver);
}

function glyphPixels(Framebuffer $b): array
{
    $out = [];
    for ($y = 0; $y < $b->viewportHeight(); $y++) {
        for ($x = 0; $x < $b->viewportWidth(); $x++) {
            $out[$y][$x] = $b->getPixel($x, $y);
        }
    }

    return $out;
}

function drawGlyphs(Framebuffer $b, float $x, float $y, ?Edges $edges = null): void
{
    (new VelvetGE($b, rasterize()->driver(), $edges))->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('A', $x, $y, Color::hex('#ffcc00'), glyphFont()));
}

it('paints a glyph mask with its coverage', function () {
    $buffer = framebuffers()->driver('native')->full(FormatSpec::rgba8(), 8, 8);
    drawGlyphs($buffer, 1, 1, Edges::ANTIALIASED);

    $twin = framebuffers()->driver('native')->full(FormatSpec::rgba8(), 8, 8);
    (new VelvetGE($twin, rasterize()->driver()))->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0)));
    $mask = (new FakeFontDriver)->mask('fake#0', 8.0, 65, 0);
    for ($row = 0; $row < $mask->height; $row++) {
        for ($col = 0; $col < $mask->width; $col++) {
            $twin->paintSpans(Spans::pack(1 + $row, 1 + $col, 1, ord($mask->coverage[$row * $mask->width + $col])), GLYPH_INK);
        }
    }

    expect(glyphPixels($buffer))->toBe(glyphPixels($twin))
        ->and($buffer->getPixel(2, 2))->toBe(GLYPH_INK)
        ->and($buffer->getPixel(1, 1))->not->toBe(GLYPH_INK);
});

it('draws hard edges as all or nothing', function () {
    $buffer = framebuffers()->driver('native')->full(FormatSpec::rgba8(), 8, 8);
    drawGlyphs($buffer, 1, 1, Edges::HARD);

    foreach (glyphPixels($buffer) as $y => $row) {
        foreach ($row as $x => $pixel) {
            $inside = $x >= 1 && $x < 5 && $y >= 1 && $y < 7;
            expect($pixel === GLYPH_INK)->toBe($inside, "pixel {$x},{$y}");
        }
    }
});

it('cuts glyphs that hang off the surface', function (float $x, float $y, array $box, bool $visible) {
    $buffer = framebuffers()->driver('native')->full(FormatSpec::rgba8(), 8, 8);
    drawGlyphs($buffer, $x, $y, Edges::ANTIALIASED);
    [$x0, $y0, $x1, $y1] = $box;

    $inked = 0;
    foreach (glyphPixels($buffer) as $py => $row) {
        foreach ($row as $px => $pixel) {
            $inside = $px >= $x0 && $px < $x1 && $py >= $y0 && $py < $y1;
            if (! $inside) {
                expect($pixel)->toBe(0x000000FF, "pixel {$px},{$py}");
            } elseif ($pixel !== 0x000000FF) {
                $inked++;
            }
        }
    }
    expect($inked > 0)->toBe($visible);
})->with([
    'wholly off the left' => [-5.0, -3.0, [-5, -3, -1, 3], false],
    'over the top left corner' => [-2.0, -3.0, [-2, -3, 2, 3], true],
    'past the right edge' => [6.0, 0.0, [6, 0, 10, 6], true],
]);

it('draws the same bytes through native and extended framebuffers', function (RasterizeDriver $raster, FramebufferDriver $framebuffers) {
    $reference = framebuffers()->driver('native')->full(FormatSpec::rgba8(), 8, 8);
    drawGlyphs($reference, 2, 1);
    $buffer = $framebuffers->full(FormatSpec::rgba8(), 8, 8);
    (new VelvetGE($buffer, $raster))->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('A', 2, 1, Color::hex('#ffcc00'), glyphFont()));

    expect(glyphPixels($buffer))->toBe(glyphPixels($reference));
})->with('engine pairings');

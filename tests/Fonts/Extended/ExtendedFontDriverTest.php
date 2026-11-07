<?php

declare(strict_types=1);

use Surface\Contracts\Fonts\FontException;
use Surface\Contracts\Fonts\ShapedGlyph;
use Surface\Fonts\Extended\ExtendedFontDriver;

/*
 * The extended driver: fontconfig matches, HarfBuzz shapes, FreeType masks. Extension-only.
 */

$loaded = fn (): bool => function_exists('FT_Init_FreeType') && function_exists('hb_shape') && function_exists('FcFontMatch');

beforeEach(function () use ($loaded): void {
    if ($loaded()) {
        $this->driver = new ExtendedFontDriver();
    }
});

afterEach(fn () => isset($this->driver) && $this->driver->close());

it('shapes through fontconfig, HarfBuzz and FreeType', function (): void {
    $run = $this->driver->shape('monospace', 16.0, 'AV');

    expect($this->driver->driver())->toBe('extended')
        ->and($run->glyphs)->toHaveCount(2)
        ->and(array_map(fn (ShapedGlyph $g) => $g->cluster, $run->glyphs))->toBe([0, 1])
        ->and($run->advance)->toBeGreaterThan(0)
        ->and($run->lineHeight)->toBeGreaterThanOrEqual($run->ascent + $run->descent - 1);
})->skip(! $loaded(), 'needs the font exts');

it('masks a glyph it shaped', function (): void {
    $g = $this->driver->shape('monospace', 16.0, 'A')->glyphs[0];
    $mask = $this->driver->mask($g->face, 16.0, $g->glyph, $g->style);

    expect($mask->width)->toBeGreaterThan(0)
        ->and($mask->height)->toBeGreaterThan(0)
        ->and(strlen($mask->coverage))->toBe($mask->width * $mask->height)
        ->and($mask->top)->toBeGreaterThan(0);
})->skip(! $loaded(), 'needs the font exts');

it('slants and thickens only what the matched face lacks', function (): void {
    $plain = $this->driver->shape('monospace', 16.0, 'AV')->glyphs;
    $styled = $this->driver->shape('monospace:slant=100:weight=200', 16.0, 'AV')->glyphs;
    if ($styled[0]->face !== $plain[0]->face) {
        $this->markTestSkipped('monospace has a real bold or italic face here.');
    }

    expect(array_map(fn (ShapedGlyph $g) => $g->style, $styled))->each->toBe(ShapedGlyph::OBLIQUE | ShapedGlyph::EMBOLDEN)
        ->and(array_map(fn (ShapedGlyph $g) => $g->style, $plain))->each->toBe(0);
})->skip(! $loaded(), 'needs the font exts');

it('draws the primary .notdef for a codepoint no font has', function (): void {
    $glyphs = $this->driver->shape('monospace', 16.0, "\u{10FFFF}")->glyphs;

    expect($glyphs)->toHaveCount(1)
        ->and($glyphs[0]->glyph)->toBe(0);
})->skip(! $loaded(), 'needs the font exts');

it('refuses to start without the font exts', function (): void {
    expect(fn () => new ExtendedFontDriver())->toThrow(FontException::class, 'ext-freetype');
})->skip($loaded(), 'the font exts are loaded');

it('outlines O as two closed contours inside its mask box', function (): void {
    $g = $this->driver->shape('monospace', 32.0, 'O')->glyphs[0];
    $contours = $this->driver->outline($g->face, 32.0, $g->glyph, $g->style);
    $mask = $this->driver->mask($g->face, 32.0, $g->glyph, $g->style);
    $outside = [];
    foreach ($contours as $contour) {
        foreach ($contour as [$x, $y]) {
            if ($x < $mask->left - 1 || $x > $mask->left + $mask->width + 1 || $y < -$mask->top - 1 || $y > -$mask->top + $mask->height + 1) {
                $outside[] = [$x, $y];
            }
        }
    }

    expect($contours)->toHaveCount(2)
        ->and(array_map(fn (array $c) => $c[0] == end($c), $contours))->toBe([true, true])
        ->and(count($contours[0]))->toBeGreaterThan(8)
        ->and($outside)->toBe([]);
})->skip(! $loaded(), 'needs the font exts');

it('outlines a space as nothing', function (): void {
    $g = $this->driver->shape('monospace', 32.0, ' ')->glyphs[0];

    expect($this->driver->outline($g->face, 32.0, $g->glyph, $g->style))->toBe([]);
})->skip(! $loaded(), 'needs the font exts');

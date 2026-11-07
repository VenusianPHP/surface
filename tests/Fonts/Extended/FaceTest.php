<?php

declare(strict_types=1);

use Surface\Fonts\Extended\FaceCache;
use Surface\Fonts\Extended\FontCatalog;
use Surface\Contracts\Fonts\GlyphMask;

/*
 * FreeType faces from FaceCache: line metrics in pixels and anti-aliased glyph masks, cached.
 */

beforeEach(function (): void {
    if (! extension_loaded('freetype') || ! extension_loaded('fontconfig') || ! extension_loaded('harfbuzz')) {
        $this->markTestSkipped('ext-freetype, ext-harfbuzz and ext-fontconfig are not loaded (run with php 8.4).');
    }
    $this->catalog = new FontCatalog();
    $this->faces = new FaceCache();
    $this->file = $this->catalog->match('sans-serif');
});

afterEach(function (): void {
    isset($this->faces) && $this->faces->close();
    isset($this->catalog) && $this->catalog->close();
});

it('gives line metrics in pixels', function (): void {
    $face = $this->faces->face($this->file, 13);

    expect($face->ascent())->toBeGreaterThan(0)
        ->and($face->descent())->toBeGreaterThan(0)
        ->and($face->lineHeight())->toBeGreaterThanOrEqual($face->ascent() + $face->descent() - 1);
});

it('renders an anti-aliased mask', function (): void {
    $face = $this->faces->face($this->file, 13);
    $mask = $face->mask($face->glyphIndex(ord('H')));
    $grey = array_filter(unpack('C*', $mask->coverage), fn (int $b) => $b !== 0 && $b !== 255);

    expect($mask)->toBeInstanceOf(GlyphMask::class)
        ->and($mask->width)->toBeGreaterThan(0)
        ->and(strlen($mask->coverage))->toBe($mask->width * $mask->height)
        ->and($grey)->not->toBeEmpty();
});

it('gives space an empty mask', function (): void {
    $face = $this->faces->face($this->file, 13);
    $mask = $face->mask($face->glyphIndex(ord(' ')));

    expect($mask->width === 0 || trim($mask->coverage, "\0") === '')->toBeTrue();
});

it('rasterises at the asked size, not scaled up', function (): void {
    $small = $this->faces->face($this->file, 13);
    $large = $this->faces->face($this->file, 26);
    $ratio = $large->mask($large->glyphIndex(ord('H')))->height / $small->mask($small->glyphIndex(ord('H')))->height;

    expect($ratio)->toBeGreaterThan(1.8)->toBeLessThan(2.2);
});

it('returns the same face for the same file and size', function (): void {
    expect($this->faces->face($this->file, 13))->toBe($this->faces->face($this->file, 13.0001))
        ->and($this->faces->face($this->file, 13))->not->toBe($this->faces->face($this->file, 14));
});

it('opens no face while masks repeat', function (): void {
    $face = $this->faces->face($this->file, 13);
    $glyphs = [$face->glyphIndex(ord('a')), $face->glyphIndex(ord('b')), $face->glyphIndex(ord('c'))];
    $opened = $this->faces->opened;
    for ($i = 0; $i < 10_000; $i++) {
        $face->mask($glyphs[$i % 3]);
    }

    expect($this->faces->opened)->toBe($opened)
        ->and($face->hbFont())->toBe($face->hbFont());
});

it('closes twice without throwing', function (): void {
    $this->faces->face($this->file, 13)->hbFont();
    $this->faces->close();
    $this->faces->close();
})->throwsNoExceptions();

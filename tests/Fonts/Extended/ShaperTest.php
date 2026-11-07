<?php

declare(strict_types=1);

use Surface\Contracts\Fonts\ShapedGlyph;
use Surface\Fonts\Extended\FaceCache;
use Surface\Fonts\Extended\FontCatalog;
use Surface\Fonts\Extended\FontFile;
use Surface\Fonts\Extended\Shaper;

/*
 * Shaper: graphemes to faces (primary, fontconfig fallback, else .notdef), then HarfBuzz per run of one face.
 */

beforeEach(function (): void {
    if (! extension_loaded('freetype') || ! extension_loaded('fontconfig') || ! extension_loaded('harfbuzz') || ! extension_loaded('intl')) {
        $this->markTestSkipped('ext-freetype, ext-harfbuzz, ext-fontconfig and intl are not loaded (run with php 8.4).');
    }
    $this->catalog = new FontCatalog();
    $this->faces = new FaceCache();
    $this->shaper = new Shaper($this->catalog, $this->faces);
});

afterEach(function (): void {
    isset($this->shaper) && $this->shaper->close();
    isset($this->faces) && $this->faces->close();
    isset($this->catalog) && $this->catalog->close();
});

it('shapes nothing to no glyphs with the face line height', function (): void {
    $run = $this->shaper->shape('sans-serif', 13.0, '');

    expect($run->glyphs)->toBe([])
        ->and($run->advance)->toBe(0.0)
        ->and($run->lineHeight)->toBeGreaterThan(0)
        ->and($run->text)->toBe('');
});

it('ligates fi where the face has it, with clusters that only grow', function (): void {
    foreach (['Times New Roman', 'Georgia', 'Helvetica Neue', 'Baskerville', 'DejaVu Serif', 'Noto Serif'] as $family) {
        $run = $this->shaper->shape($family, 13.0, 'office');
        if (count($run->glyphs) < strlen('office')) {
            $clusters = array_map(fn (ShapedGlyph $g) => $g->cluster, $run->glyphs);
            $sorted = $clusters;
            sort($sorted);
            expect($clusters)->toBe($sorted);

            return;
        }
    }
    $this->markTestSkipped('No installed face ligates "fi".');
});

it('kerns AV tighter than A and V apart', function (): void {
    $pair = $this->shaper->shape('sans-serif', 13.0, 'AV')->advance;
    $apart = $this->shaper->shape('sans-serif', 13.0, 'A')->advance + $this->shaper->shape('sans-serif', 13.0, 'V')->advance;
    if ($pair == $apart) {
        $this->markTestSkipped('The sans-serif face does not kern AV.');
    }

    expect($pair)->toBeLessThan($apart);
});

it('sends a character the primary face lacks to a fallback face', function (): void {
    $glyphs = $this->shaper->shape('sans-serif', 13.0, "a\u{4E2D}b")->glyphs;

    expect($glyphs)->toHaveCount(3)
        ->and($glyphs[1]->face)->not->toBe($glyphs[0]->face)
        ->and($glyphs[2]->face)->toBe($glyphs[0]->face)
        ->and(array_map(fn ($g) => $g->cluster, $glyphs))->toBe([0, 1, 4]);
});

it('shapes invalid UTF-8 without throwing and keeps byte clusters', function (): void {
    $glyphs = $this->shaper->shape('sans-serif', 13.0, "\xFF\xFEok")->glyphs;

    expect($glyphs)->not->toBeEmpty()
        ->and(end($glyphs)->cluster)->toBe(3);
});

it('draws a codepoint no font has as one .notdef from the primary face', function (): void {
    $primary = $this->shaper->shape('sans-serif', 13.0, 'a')->glyphs[0]->face;
    $glyphs = $this->shaper->shape('sans-serif', 13.0, "\u{10FFFD}")->glyphs;

    expect($glyphs)->toHaveCount(1)
        ->and($glyphs[0]->face)->toBe($primary)
        ->and($glyphs[0]->glyph)->toBe(0);
});

it('doubles the width at twice the pixel size', function (): void {
    $one = $this->shaper->shape('sans-serif', 13.0, 'Zoom out')->advance;
    $two = $this->shaper->shape('sans-serif', 26.0, 'Zoom out')->advance;

    expect($two / $one)->toBeGreaterThan(1.9)->toBeLessThan(2.1);
});

it('keeps the last 512 shapes and reuses them', function (): void {
    $first = $this->shaper->shape('sans-serif', 13.0, 'same');

    expect($this->shaper->shape('sans-serif', 13.0, 'same'))->toBe($first)
        ->and($this->shaper->shape('sans-serif', 14.0, 'same'))->not->toBe($first);
});

it('renders a synthetic bold and italic mask that differs from the plain one', function (): void {
    $mask = function (string $pattern): string {
        $g = $this->shaper->shape($pattern, 13.0, 'H')->glyphs[0];
        $at = strrpos($g->face, '#');

        return $this->faces->face(new FontFile(substr($g->face, 0, $at), (int) substr($g->face, $at + 1)), 13.0)->mask($g->glyph, $g->style)->coverage;
    };
    $styled = $this->shaper->shape('monospace:weight=bold:slant=italic', 13.0, 'H')->glyphs[0];
    if ($styled->style === 0) {
        $this->markTestSkipped('monospace has a real bold italic face.');
    }

    expect($mask('monospace:weight=bold:slant=italic'))->not->toBe($mask('monospace'));
});

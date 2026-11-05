<?php

declare(strict_types=1);

use Surface\Text\FaceCache;
use Surface\Text\FontCatalog;
use Surface\Text\ShapedGlyph;
use Surface\Text\Shaper;
use Surface\Text\TextStyle;

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
    $this->style = new TextStyle();
});

afterEach(function (): void {
    isset($this->shaper) && $this->shaper->close();
    isset($this->faces) && $this->faces->close();
    isset($this->catalog) && $this->catalog->close();
});

it('shapes nothing to no glyphs with the face line height', function (): void {
    $run = $this->shaper->shape('', $this->style);

    expect($run->glyphs())->toBe([])
        ->and($run->width())->toBe(0.0)
        ->and($run->lineHeight())->toBeGreaterThan(0)
        ->and($run->text())->toBe('');
});

it('ligates fi where the face has it, with clusters that only grow', function (): void {
    foreach (['Times New Roman', 'Georgia', 'Helvetica Neue', 'Baskerville', 'DejaVu Serif', 'Noto Serif'] as $family) {
        $run = $this->shaper->shape('office', new TextStyle($family));
        if (count($run->glyphs()) < strlen('office')) {
            $clusters = array_map(fn (ShapedGlyph $g) => $g->cluster, $run->glyphs());
            $sorted = $clusters;
            sort($sorted);
            expect($clusters)->toBe($sorted);

            return;
        }
    }
    $this->markTestSkipped('No installed face ligates "fi".');
});

it('kerns AV tighter than A and V apart', function (): void {
    $pair = $this->shaper->shape('AV', $this->style)->width();
    $apart = $this->shaper->shape('A', $this->style)->width() + $this->shaper->shape('V', $this->style)->width();
    if ($pair == $apart) {
        $this->markTestSkipped('The sans-serif face does not kern AV.');
    }

    expect($pair)->toBeLessThan($apart);
});

it('sends a character the primary face lacks to a fallback face', function (): void {
    $glyphs = $this->shaper->shape("a\u{4E2D}b", $this->style)->glyphs();

    expect($glyphs)->toHaveCount(3)
        ->and($glyphs[1]->face)->not->toBe($glyphs[0]->face)
        ->and($glyphs[2]->face)->toBe($glyphs[0]->face)
        ->and(array_map(fn ($g) => $g->cluster, $glyphs))->toBe([0, 1, 4]);
});

it('shapes invalid UTF-8 without throwing and keeps byte clusters', function (): void {
    $glyphs = $this->shaper->shape("\xFF\xFEok", $this->style)->glyphs();

    expect($glyphs)->not->toBeEmpty()
        ->and(end($glyphs)->cluster)->toBe(3);
});

it('draws a codepoint no font has as one .notdef from the primary face', function (): void {
    $primary = $this->shaper->shape('a', $this->style)->glyphs()[0]->face;
    $glyphs = $this->shaper->shape("\u{10FFFD}", $this->style)->glyphs();

    expect($glyphs)->toHaveCount(1)
        ->and($glyphs[0]->face)->toBe($primary)
        ->and($glyphs[0]->glyph)->toBe(0);
});

it('doubles the width at scale 2', function (): void {
    $one = $this->shaper->shape('Zoom out', $this->style)->width();
    $two = $this->shaper->shape('Zoom out', $this->style, 2.0)->width();

    expect($two / $one)->toBeGreaterThan(1.9)->toBeLessThan(2.1);
});

it('keeps the last 512 shapes and reuses them', function (): void {
    $first = $this->shaper->shape('same', $this->style);

    expect($this->shaper->shape('same', $this->style))->toBe($first)
        ->and($this->shaper->shape('same', new TextStyle(size: 14)))->not->toBe($first);
});

it('names the fontconfig pattern of a style', function (): void {
    expect((new TextStyle('Menlo', 13, 700, true))->pattern())->toBe('Menlo:weight=bold:slant=italic')
        ->and((new TextStyle())->pattern())->toBe('sans-serif:weight=regular');
});

it('renders a synthetic bold and italic mask that differs from the plain one', function (): void {
    $plain = $this->shaper->shape('H', $this->style)->glyphs()[0]->mask();
    $styled = $this->shaper->shape('H', new TextStyle(weight: 700, italic: true))->glyphs()[0]->mask();

    expect($styled->coverage)->not->toBe($plain->coverage);
});

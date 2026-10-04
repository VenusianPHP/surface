<?php

use Surface\Contracts\Fonts\FontEncoding;
use Surface\Contracts\Fonts\GFXFont;
use Surface\Contracts\Fonts\YOffsetMode;
use Surface\Drawing\Text\PlacedGlyph;
use Surface\Drawing\Text\Typesetter;
use Surface\Fonts\ClassicFont;
use Venusian\Surface\Tests\Support\Fakes\TinyAAFace;
use Venusian\Surface\Tests\Support\Fakes\TinyFace;

it('lays out an Adafruit face from the line top, ascent folded in', function () {
    $t = new Typesetter();
    $face = new TinyFace();

    expect($t->ascent($face))->toBe(3);
    $placed = $t->layout($face, "AB\r\nCZ");
    expect($placed)->toHaveCount(3)
        ->and($placed[0])->toEqual(new PlacedGlyph(0x41, 0, 0, $face->glyph(0x41)))
        ->and($placed[1])->toEqual(new PlacedGlyph(0x42, 4, 0, $face->glyph(0x42)))
        ->and($placed[2])->toEqual(new PlacedGlyph(0x43, 0, 9, $face->glyph(0x43)))
        ->and($t->bounds($face, "AB\nC"))->toBe([0, 0, 7, 12])
        ->and($t->bounds($face, "Z\r"))->toBe([0, 0, 0, 0]);
});

it('decodes 1bpp glyph rows into inclusive runs', function () {
    $t = new Typesetter();
    $face = new TinyFace();

    expect($t->runs($face, $face->glyph(0x41)))->toBe([[0, 0, 2], [1, 0, 0], [1, 2, 2], [2, 0, 2]])
        ->and($t->runs($face, $face->glyph(0x42)))->toBe([[0, 1, 1], [1, 0, 2], [2, 1, 1]])
        ->and($t->runs($face, $face->glyph(0x43)))->toBe([[0, 0, 1], [1, 0, 0], [2, 0, 1]])
        ->and($t->coverage($face, $face->glyph(0x43)))->toBe("\xff\xff\xff\x00\xff\xff");
});

it("decodes the classic column-major 'A' into its seven rows", function () {
    $t = new Typesetter();
    $face = new ClassicFont();

    expect($t->ascent($face))->toBe(0)
        ->and($t->runs($face, $face->glyph(0x41)))->toBe([
            [0, 2, 2],
            [1, 1, 1], [1, 3, 3],
            [2, 0, 0], [2, 4, 4],
            [3, 0, 0], [3, 4, 4],
            [4, 0, 4],
            [5, 0, 0], [5, 4, 4],
            [6, 0, 0], [6, 4, 4],
        ])
        ->and($t->layout($face, 'AB')[1]->x)->toBe(6);
});

it('thresholds 4bpp nibbles and measures LINE faces from the bottom', function () {
    $t = new Typesetter();
    $face = new TinyAAFace();

    expect($face->encoding())->toBe(FontEncoding::LVGL)
        ->and($face->yOffsetMode())->toBe(YOffsetMode::LINE)
        ->and($t->coverage($face, $face->glyph(0x41)))->toBe(chr(255).chr(51).chr(153).chr(0))
        ->and($t->runs($face, $face->glyph(0x41)))->toBe([[0, 0, 0], [1, 0, 0]])
        ->and($t->ascent($face))->toBe(0)
        ->and($t->layout($face, 'A')[0]->y)->toBe(6);
});

it('nudges short RAW LVGL glyphs down by the cap height and normalises a negative top', function () {
    $face = new class extends GFXFont {
        protected int $first = 0x41;
        protected int $last = 0x43;
        protected int $y_advance = 8;
        protected array $bitmaps = [0xFF, 0xFF, 0xFF, 0xFF];
        protected array $glyphs = [[0, 0, 0, 0, 0, 0], [0, 2, 4, 3, 0, 0], [1, 2, 2, 3, 0, 0], [2, 2, 2, 3, 0, -1]];
    };
    $t = new Typesetter();

    expect($face->yOffsetMode())->toBe(YOffsetMode::RAW)
        ->and($face->capHeight())->toBe(4)
        ->and($t->ascent($face))->toBe(1)
        ->and(array_map(fn (PlacedGlyph $p) => $p->y, $t->layout($face, 'ABC')))->toBe([1, 3, 0]);
});

it('runs are cached per face class and glyph offset', function () {
    $t = new Typesetter();
    $face = new TinyFace();
    $a = $t->runs($face, $face->glyph(0x41));

    expect($t->runs(new TinyFace(), $face->glyph(0x41)))->toBe($a);
});

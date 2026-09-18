<?php

use Surface\Contracts\Fonts\FontException;
use Surface\Contracts\Fonts\GFXFont;
use Surface\Drawing\Text\GlyphAtlas;
use Surface\Drawing\Text\Typesetter;
use Venusian\Surface\Tests\Support\Fakes\TinyFace;

it('shelf-packs every glyph with a one-texel gutter into white-with-coverage texels', function () {
    $atlas = GlyphAtlas::bake(new Typesetter(), new TinyFace(), 64);

    expect($atlas->width())->toBe(64)->and($atlas->height())->toBe(8)
        ->and(strlen($atlas->rgba8()))->toBe(64 * 8 * 4)
        ->and($atlas->rect(0x41))->toBe([1, 1, 3, 3])
        ->and($atlas->rect(0x42))->toBe([5, 1, 3, 3])
        ->and($atlas->rect(0x43))->toBe([9, 1, 2, 3])
        ->and($atlas->rect(0x44))->toBeNull();

    $texel = fn (int $x, int $y) => array_values(unpack('C4', substr($atlas->rgba8(), ($y * 64 + $x) * 4, 4)));
    expect($texel(1, 1))->toBe([255, 255, 255, 255])   // A top-left
        ->and($texel(2, 2))->toBe([255, 255, 255, 0])  // A's hole
        ->and($texel(0, 0))->toBe([255, 255, 255, 0])  // gutter
        ->and($texel(6, 1))->toBe([255, 255, 255, 255]); // B's top
});

it('grows the width before the height and throws past the executor limit', function () {
    $wide = new class extends GFXFont {
        protected int $first = 0x41;
        protected int $last = 0x41;
        protected array $bitmaps = [0xFF];
        protected array $glyphs = [[0, 3, 1, 4, 0, -1]];
    };

    expect(fn () => GlyphAtlas::bake(new Typesetter(), $wide, 4))->toThrow(FontException::class)
        ->and(GlyphAtlas::bake(new Typesetter(), $wide, 8)->width())->toBe(8);
});

it('a face without bitmap data bakes one transparent texel', function () {
    $empty = new class extends GFXFont {};
    $atlas = GlyphAtlas::bake(new Typesetter(), $empty, 64);

    expect($atlas->width())->toBe(1)->and($atlas->height())->toBe(1)
        ->and($atlas->rgba8())->toBe("\0\0\0\0")
        ->and($atlas->rect(0x41))->toBeNull();
});

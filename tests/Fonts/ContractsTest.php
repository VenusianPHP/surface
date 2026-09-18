<?php

use Surface\Contracts\Core\SurfaceLevelException;
use Surface\Contracts\Fonts\FontEncoding;
use Surface\Contracts\Fonts\FontException;
use Surface\Contracts\Fonts\GFXFont;
use Surface\Contracts\Fonts\Glyph;
use Surface\Contracts\Fonts\YOffsetMode;

it('Glyph is a readonly value', function () {
    $g = new Glyph(12, 3, 4, 5, 1, -2);

    expect($g->bitmap_offset)->toBe(12)->and($g->x_offset)->toBe(1)->and($g->y_offset)->toBe(-2)
        ->and((new ReflectionClass($g))->isReadOnly())->toBeTrue();
});

it('an Adafruit face answers glyphs by code and null outside its range', function () {
    $face = new class extends GFXFont {
        protected int $first = 0x41;
        protected int $last = 0x42;
        protected int $y_advance = 10;
        protected array $bitmaps = [0xFF];
        protected array $glyphs = [[0, 3, 4, 5, 0, -4], [0, 2, 2, 3, 1, -2]];
    };

    expect($face->encoding())->toBe(FontEncoding::ADAFRUIT)
        ->and($face->yOffsetMode())->toBe(YOffsetMode::RAW)
        ->and($face->lineHeight())->toBe(10)
        ->and($face->glyph(0x41))->toEqual(new Glyph(0, 3, 4, 5, 0, -4))
        ->and($face->glyph(0x42)?->x_offset)->toBe(1)
        ->and($face->glyph(0x40))->toBeNull()
        ->and($face->glyph(0x43))->toBeNull()
        ->and($face->hasBitmapData())->toBeTrue()
        ->and($face->byte(0))->toBe(0xFF)
        ->and($face->byte(99))->toBe(0);
});

it('detects LVGL from the reserved glyph 0 and applies the +1 shift', function () {
    $face = new class extends GFXFont {
        protected int $first = 0x41;
        protected int $last = 0x41;
        protected array $bitmaps = [0xF0];
        protected array $glyphs = [[0, 0, 0, 0, 0, 0], [0, 2, 2, 3, 0, 1]];
    };

    expect($face->encoding())->toBe(FontEncoding::LVGL)
        ->and($face->yOffsetMode())->toBe(YOffsetMode::LINE)
        ->and($face->glyph(0x41))->toEqual(new Glyph(0, 2, 2, 3, 0, 1));
});

it('an LVGL face with a negative offset is RAW', function () {
    $face = new class extends GFXFont {
        protected int $first = 0x41;
        protected int $last = 0x42;
        protected array $bitmaps = [0xF0];
        protected array $glyphs = [[0, 0, 0, 0, 0, 0], [0, 2, 2, 3, 0, 0], [0, 2, 2, 3, 0, -1]];
    };

    expect($face->encoding())->toBe(FontEncoding::LVGL)->and($face->yOffsetMode())->toBe(YOffsetMode::RAW);
});

it('an explicit encoding wins over detection', function () {
    $face = new class extends GFXFont {
        protected int $first = 0x41;
        protected int $last = 0x41;
        protected ?FontEncoding $encoding = FontEncoding::LVGL;
        protected ?YOffsetMode $y_offset_mode = YOffsetMode::RAW;
        protected array $bitmaps = [0xF0];
        protected array $glyphs = [[0, 0, 0, 0, 0, 0], [0, 2, 2, 3, 0, 5]];
    };

    expect($face->encoding())->toBe(FontEncoding::LVGL)->and($face->yOffsetMode())->toBe(YOffsetMode::RAW);
});

it('a column-major face with data synthesises the classic 5x8 glyph', function () {
    $face = new class extends GFXFont {
        protected int $first = 0;
        protected int $last = 255;
        protected bool $column_major = true;
        protected array $bitmaps = [0x01, 0x02, 0x03, 0x04, 0x05];
    };
    $empty = new class extends GFXFont {
        protected bool $column_major = true;
    };

    expect($face->glyph(1))->toEqual(new Glyph(5, 5, 8, 6, 0, 0))
        ->and($empty->glyph(0x41))->toBeNull()
        ->and($empty->hasBitmapData())->toBeFalse();
});

it('capHeight is the tallest capital, falling back to the line height', function () {
    $face = new class extends GFXFont {
        protected int $first = 0x41;
        protected int $last = 0x43;
        protected int $y_advance = 9;
        protected array $bitmaps = [0xFF];
        protected array $glyphs = [[0, 2, 5, 3, 0, -5], [0, 2, 7, 3, 0, -7], [0, 2, 6, 3, 0, -6]];
    };
    $symbols = new class extends GFXFont {
        protected int $first = 0x21;
        protected int $last = 0x21;
        protected int $y_advance = 9;
        protected array $bitmaps = [0xFF];
        protected array $glyphs = [[0, 1, 3, 2, 0, -3]];
    };

    expect($face->capHeight())->toBe(7)->and($symbols->capHeight())->toBe(9);
});

it('FontException is a Surface-level exception with four shapes', function () {
    expect(FontException::unknown('nope'))->toBeInstanceOf(SurfaceLevelException::class)
        ->and(FontException::unknown('nope')->getMessage())->toContain('nope')
        ->and(FontException::notAFont('Foo')->getMessage())->toContain('Foo')
        ->and(FontException::invalidHeader('no Bitmaps[]')->getMessage())->toBe('no Bitmaps[]')
        ->and(FontException::atlasTooLarge('Foo', 8192, 4096)->getMessage())->toContain('4096');
});

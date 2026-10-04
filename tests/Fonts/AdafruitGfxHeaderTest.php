<?php

use Surface\Contracts\Fonts\FontException;
use Surface\Fonts\Support\AdafruitGfxHeader;

it('parses a FreeSans9pt7b-shaped header', function () {
    $header = <<<'H'
const uint8_t FreeSans9pt7bBitmaps[] PROGMEM = {
    0xFF, 0xFF, 0xF8, 0xC0
};

const GFXglyph FreeSans9pt7bGlyphs[] PROGMEM = {
    {0, 0, 0, 5, 0, 1},        // 0x20 ' '
    {0, 2, 13, 6, 2, -12}     // 0x21 '!'
};

const GFXfont FreeSans9pt7b PROGMEM = {(uint8_t *)FreeSans9pt7bBitmaps,
                                       (GFXglyph *)FreeSans9pt7bGlyphs, 0x20,
                                       0x21, 22};
H;

    $parsed = AdafruitGfxHeader::parse($header);

    expect($parsed['first'])->toBe(0x20)
        ->and($parsed['last'])->toBe(0x21)
        ->and($parsed['yAdvance'])->toBe(22)
        ->and($parsed['bitmaps'])->toBe([0xFF, 0xFF, 0xF8, 0xC0])
        ->and($parsed['glyphs'])->toHaveCount(2)
        ->and($parsed['glyphs'][1][1])->toBe(2)
        ->and($parsed['glyphs'][1][5])->toBe(-12);
});

it('refuses a header without the three tables', function () {
    expect(fn () => AdafruitGfxHeader::parse(''))->toThrow(FontException::class)
        ->and(fn () => AdafruitGfxHeader::parse('const uint8_t xBitmaps[] PROGMEM = { 0x00 };'))->toThrow(FontException::class);
});

it('renders a loadable GFXFont subclass in house shape', function () {
    $php = AdafruitGfxHeader::renderClassSource('App\\Fonts', 'TinyTestFont', [
        'first' => 0x20,
        'last' => 0x20,
        'yAdvance' => 8,
        'bitmaps' => [0xAA],
        'glyphs' => [[0, 1, 1, 2, 0, 0, 'comment' => "0x20 ' '"]],
    ]);

    expect($php)->toContain('namespace App\\Fonts;')
        ->and($php)->toContain('use Surface\\Contracts\\Fonts\\GFXFont;')
        ->and($php)->toContain('class TinyTestFont extends GFXFont')
        ->and($php)->toContain('protected int $y_advance = 8;')
        ->and($php)->toContain('0xAA')
        ->and($php)->not->toContain('getClass')
        ->and($php)->not->toContain('yAdvance');

    $path = sys_get_temp_dir().'/surface-font-'.uniqid().'.php';
    file_put_contents($path, $php);
    try {
        require $path;
        $face = new App\Fonts\TinyTestFont();
        expect($face->lineHeight())->toBe(8)->and($face->glyph(0x20)?->x_advance)->toBe(2);
    } finally {
        unlink($path);
    }
});

<?php

use Surface\Contracts\Fonts\FontEncoding;
use Surface\Contracts\Fonts\Glyph;
use Surface\Fonts\ClassicFont;

it('is the Adafruit 5x7 table, column-major, 256 codes', function () {
    $face = new ClassicFont();

    expect($face->first())->toBe(0)->and($face->last())->toBe(255)
        ->and($face->lineHeight())->toBe(8)
        ->and($face->isColumnMajor())->toBeTrue()
        ->and($face->encoding())->toBe(FontEncoding::ADAFRUIT)
        ->and($face->hasBitmapData())->toBeTrue()
        ->and($face->glyph(0x41))->toEqual(new Glyph(0x41 * 5, 5, 8, 6, 0, 0));
});

it("holds the classic 'A' columns byte for byte", function () {
    $face = new ClassicFont();
    $o = 0x41 * 5;

    expect([$face->byte($o), $face->byte($o + 1), $face->byte($o + 2), $face->byte($o + 3), $face->byte($o + 4)])
        ->toBe([0x7C, 0x12, 0x11, 0x12, 0x7C]);
});

<?php

use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Views\Color;

it('builds from 0-255 channels into 0-1 floats', function () {
    $color = Color::rgb(255, 85, 0);

    expect($color->red)->toBe(1.0)
        ->and($color->green)->toEqualWithDelta(0.3333, 0.001)
        ->and($color->blue)->toBe(0.0)
        ->and($color->alpha)->toBe(1.0);
});

it('parses six digit hex', function () {
    $color = Color::hex('#ff5500');

    expect($color->red)->toBe(1.0)
        ->and($color->green)->toEqualWithDelta(0.3333, 0.001)
        ->and($color->blue)->toBe(0.0)
        ->and($color->alpha)->toBe(1.0);
});

it('parses three digit hex', function () {
    $color = Color::hex('#f50');

    expect($color->red)->toBe(1.0)
        ->and($color->green)->toEqualWithDelta(0.3333, 0.001)
        ->and($color->blue)->toBe(0.0);
});

it('parses eight digit hex with alpha', function () {
    $color = Color::hex('#ff550080');

    expect($color->alpha)->toEqualWithDelta(0.502, 0.001);
});

it('rejects malformed hex', function () {
    Color::hex('ff5500');
})->throws(ViewException::class);

it('clamps channels to the 0-1 range', function () {
    $color = new Color(2.0, -1.0, 0.5, 5.0);

    expect($color->red)->toBe(1.0)
        ->and($color->green)->toBe(0.0)
        ->and($color->blue)->toBe(0.5)
        ->and($color->alpha)->toBe(1.0);
});

it('renders GTK rgba css', function () {
    expect(Color::rgb(255, 85, 0)->toRgbaCss())->toBe('rgba(255,85,0,1.000)')
        ->and(Color::hex('#ff550080')->toRgbaCss())->toBe('rgba(255,85,0,0.502)');
});

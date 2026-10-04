<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Region;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

/*
 * writeRgba8() puts a block of RGBA8 pixels (top-left first) onto the surface
 * at (x, y): each pixel replaces what is there, mapped into the host format,
 * and whatever falls off the surface is dropped.
 */

it('writes a block of pixels at an offset, replacing what is there', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 3, 2)->fill(0x11223344);
    $buffer->writeRgba8(hex2bin('ff0000ff'.'00000000'), 2, 1, 1, 1);

    expect(bin2hex($buffer->dump()))->toBe(
        '11223344'.'11223344'.'11223344'.
        '11223344'.'ff0000ff'.'00000000'
    );
})->with('framebuffer drivers');

it('drops what falls off any edge', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 2, 2);
    $block = hex2bin('01010101'.'02020202'.'03030303'.'04040404');

    expect(bin2hex($buffer->writeRgba8($block, 2, 2, -1, -1)->dump()))->toBe('04040404'.'00000000'.'00000000'.'00000000')
        ->and(bin2hex($buffer->clear()->writeRgba8($block, 2, 2, 1, 1)->dump()))->toBe('00000000'.'00000000'.'00000000'.'01010101')
        ->and(bin2hex($buffer->clear()->writeRgba8($block, 2, 2, 2, 0)->dump()))->toBe(str_repeat('00000000', 4));
})->with('framebuffer drivers');

it('maps the pixels into the host format', function (FramebufferDriver $driver): void {
    $rgb565 = $driver->full(Formats::rgb565(), 2, 1)->writeRgba8(hex2bin('ffffffff'.'ff0000ff'), 2, 1);
    $mono = $driver->full(Formats::mono(), 8, 1)->writeRgba8(hex2bin(str_repeat('ffffffff', 4).str_repeat('000000ff', 4)), 8, 1);

    expect(bin2hex($rgb565->toRgba8()))->toBe('ffffffff'.'ff0000ff')
        ->and(bin2hex($mono->dump()))->toBe('f0');
})->with('framebuffer drivers');

it('refuses a block whose bytes are not width x height x 4, writing nothing', function (FramebufferDriver $driver, string $bytes, int $width, int $height): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 2, 2);

    expect(fn () => $buffer->writeRgba8($bytes, $width, $height))->toThrow(FramebufferException::class)
        ->and(bin2hex($buffer->dump()))->toBe(str_repeat('00000000', 4));
})->with('framebuffer drivers')->with([
    'one byte short' => [str_repeat("\xff", 15), 2, 2],
    'one byte over' => [str_repeat("\xff", 17), 2, 2],
    'a zero width' => ['', 0, 2],
    'a negative height' => [str_repeat("\xff", 8), 2, -1],
]);

it('records the rect written on a dirty buffer', function (FramebufferDriver $driver): void {
    $box = fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height];
    $buffer = $driver->dirty(FormatSpec::rgba8(), 4, 4);
    $buffer->beginEpoch()->writeRgba8(str_repeat("\xff", 3 * 2 * 4), 3, 2, 2, 3);

    expect(array_map($box, $buffer->damage()))->toBe([[2, 3, 2, 1]])
        ->and(array_map($box, $buffer->beginEpoch()->writeRgba8(str_repeat("\xff", 4), 1, 1, 9, 9)->damage()))->toBe([]);
})->with('framebuffer drivers');

it('takes surface coordinates on a paged buffer and keeps the rows on the current page', function (FramebufferDriver $driver): void {
    $paged = $driver->paged(FormatSpec::rgba8(), 1, 4, 2);
    $column = hex2bin('01010101'.'02020202'.'03030303');

    $paged->setPage(0)->writeRgba8($column, 1, 3, 0, 1);
    expect(bin2hex($paged->flush(FormatSpec::rgba8())))->toBe('00000000'.'01010101');

    $paged->setPage(1)->writeRgba8($column, 1, 3, 0, 1);
    expect(bin2hex($paged->flush(FormatSpec::rgba8())))->toBe('02020202'.'03030303');
})->with('framebuffer drivers');

it('writes into the frame being drawn on a ring, not the one on show', function (FramebufferDriver $driver): void {
    $ring = $driver->ring(FormatSpec::rgba8(), 1, 1, 2);
    $ring->writeRgba8(hex2bin('0a0b0c0d'), 1, 1);

    expect(bin2hex($ring->dump()))->toBe('00000000')
        ->and(bin2hex($ring->back()->dump()))->toBe('0a0b0c0d')
        ->and(bin2hex($ring->present()->dump()))->toBe('0a0b0c0d');
})->with('framebuffer drivers');

it('writes an ePaper frame in its palette', function (FramebufferDriver $driver): void {
    $paper = $driver->epaper(Formats::mono(), 8, 1)->writeRgba8(hex2bin(str_repeat('000000ff', 8)), 8, 1);

    expect(bin2hex($paper->toRgba8()))->toBe(str_repeat('000000ff', 8));
})->with('framebuffer drivers');

<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\Spans;
use Surface\NutsAndBolts\Affine;
use Venusian\Surface\Tests\Support\Framebuffers\Rgba8Source;

/*
 * paintImage()'s tint, window and coverage, on every framebuffer driver: the bytes are the same. Each sampled pixel is multiplied by the tint (per channel,
 * c = (c × t + 127) / 255, alpha too), its alpha by the opacity the same way, then by the coverage of the
 * mask's span; a pixel outside the window or the mask is not painted. Bytes are worked by hand.
 */

function styled(int $width, int $height, string ...$pixels): Rgba8Source
{
    return new Rgba8Source(hex2bin(implode('', $pixels)), $width, $height);
}

it('multiplies a tint into each channel, alpha included', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 1, 1);
    // (255, 128, 0, 255) × (128, 255, 0, 128) = (128, 128, 0, 128); over nothing at alpha 128 that lays 64, 64, 0 at alpha 128
    $buffer->paintImage(styled(1, 1, 'ff8000ff'), Affine::identity(), tint: 0x80ff0080);

    expect(bin2hex($buffer->dump()))->toBe('40400080');
})->with('framebuffer drivers');

it('multiplies a white tint of some alpha as an opacity', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 1, 1);
    $buffer->paintImage(styled(1, 1, 'ffffffff'), Affine::identity(), tint: 0xffffff80);

    expect(bin2hex($buffer->dump()))->toBe('80808080');
})->with('framebuffer drivers');

it('paints nothing for a tint with no alpha', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 1, 1);
    $buffer->paintImage(styled(1, 1, 'ffffffff'), Affine::identity(), tint: 0xffffff00);

    expect(bin2hex($buffer->dump()))->toBe('00000000');
})->with('framebuffer drivers');

it('takes the opacity after the tint', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 1, 1);
    // alpha 255 × 128 / 255 = 128, then × 128 / 255 = 64; colour (255 × 255) stays 255
    $buffer->paintImage(styled(1, 1, 'ffffffff'), Affine::identity(), 128, tint: 0xffffff80);

    expect(bin2hex($buffer->dump()))->toBe('40404040');
})->with('framebuffer drivers');

it('paints only the window, holding to the pixels whose centre maps inside it', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 4, 1);
    $buffer->paintImage(styled(2, 1, 'ff0000ff', '00ff00ff'), Affine::scaling(2.0, 1.0), window: [1.0, 0.0, 2.0, 1.0]);

    expect(bin2hex($buffer->dump()))->toBe('00000000'.'00000000'.'00ff00ff'.'00ff00ff');
})->with('framebuffer drivers');

it('keeps the window to the part of the source that exists, and refuses a window that is not a rect', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 2, 1);
    $buffer->paintImage(styled(2, 1, 'ff0000ff', '00ff00ff'), Affine::identity(), window: [-5.0, -5.0, 1.0, 9.0]);
    expect(bin2hex($buffer->dump()))->toBe('ff0000ff'.'00000000');

    expect(fn () => $buffer->paintImage(styled(2, 1, 'ff0000ff', '00ff00ff'), Affine::identity(), window: [1.0, 0.0, 1.0, 1.0]))
        ->toThrow(FramebufferException::class, 'window');
})->with('framebuffer drivers');

it('still smooths across the window edge, reading the whole source', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 4, 1);
    // the right pixel of a 2x1 source, scaled 2x and smoothed: the first painted pixel (u = 1.25, 0.75 of the way from the black to the white centre) still blends the black pixel outside the window
    $buffer->paintImage(styled(2, 1, '000000ff', 'ffffffff'), Affine::scaling(2.0, 1.0), filter: Filter::LINEAR, window: [1.0, 0.0, 2.0, 1.0]);

    expect(bin2hex($buffer->dump()))->toBe('00000000'.'00000000'.'bfbfbfff'.'ffffffff');
})->with('framebuffer drivers');

it('scales the alpha by the coverage of the span that holds the pixel, and skips a pixel no span holds', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 3, 1);
    $buffer->paintImage(styled(3, 1, 'ffffffff', 'ffffffff', 'ffffffff'), Affine::identity(), coverage: Spans::pack(0, 1, 1, 128).Spans::pack(0, 2, 1, 255));

    expect(bin2hex($buffer->dump()))->toBe('00000000'.'80808080'.'ffffffff');
})->with('framebuffer drivers');

it('refuses coverage outside the surface', function (FramebufferDriver $driver): void {
    expect(fn () => $driver->full(FormatSpec::rgba8(), 2, 1)->paintImage(styled(2, 1, 'ffffffff', 'ffffffff'), Affine::identity(), coverage: Spans::pack(0, 1, 2)))
        ->toThrow(FramebufferException::class, 'coverage');
})->with('framebuffer drivers');

it('applies the tint, the window and the mask together', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 2, 1);
    $buffer->paintImage(styled(2, 1, 'ffffffff', 'ffffffff'), Affine::identity(), tint: 0xff000080, window: [0.0, 0.0, 2.0, 1.0], coverage: Spans::pack(0, 0, 2, 128));

    // red at alpha 255 × 128 / 255 = 128, then × 128 / 255 = 64: 255 over nothing at 64 lays 64 → 0x40
    expect(bin2hex($buffer->dump()))->toBe('40000040'.'40000040');
})->with('framebuffer drivers');

it('shifts the window and the mask with the page of a paged buffer', function (FramebufferDriver $driver): void {
    $paged = $driver->paged(FormatSpec::rgba8(), 1, 4, 2);
    $source = styled(1, 4, 'ff0000ff', '00ff00ff', '0000ffff', 'ffffffff');

    // page 1 is rows 2..3 of the 4-row surface; the window keeps surface rows 3..4, the mask only row 3
    $paged->setPage(1)->paintImage($source, Affine::identity(), window: [0.0, 3.0, 1.0, 4.0], coverage: Spans::pack(3, 0, 1, 255));

    expect(bin2hex($paged->dump()))->toBe('00000000'.'ffffffff');
})->with('framebuffer drivers');

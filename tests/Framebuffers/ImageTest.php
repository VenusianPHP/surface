<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\Region;
use Surface\NutsAndBolts\Affine;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;
use Venusian\Surface\Tests\Support\Framebuffers\Rgba8Source;

/*
 * paintImage() places a source's pixels through an affine transform: a target
 * pixel is painted when its centre maps inside the source, with the source
 * pixel under that point (NEAREST) or the four around it (LINEAR), blended by
 * source alpha × opacity. Expected bytes are worked by hand from that rule.
 */

const RED = 'ff0000ff';
const GREEN = '00ff00ff';
const BLUE = '0000ffff';
const WHITE = 'ffffffff';
const TRANSPARENT = '00000000';

function image(int $width, int $height, string ...$pixels): Rgba8Source
{
    return new Rgba8Source(hex2bin(implode('', $pixels)), $width, $height);
}

it('places a source pixel for pixel under a translation', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 4, 4);
    $buffer->paintImage(image(2, 2, RED, GREEN, BLUE, WHITE), Affine::translation(1.0, 1.0));

    expect(bin2hex($buffer->dump()))->toBe(
        TRANSPARENT.TRANSPARENT.TRANSPARENT.TRANSPARENT.
        TRANSPARENT.RED.GREEN.TRANSPARENT.
        TRANSPARENT.BLUE.WHITE.TRANSPARENT.
        TRANSPARENT.TRANSPARENT.TRANSPARENT.TRANSPARENT
    );
})->with('framebuffer drivers');

it('scales with the nearest source pixel', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 4, 2);
    $buffer->paintImage(image(2, 1, RED, GREEN), Affine::scaling(2.0, 2.0));

    expect(bin2hex($buffer->dump()))->toBe(RED.RED.GREEN.GREEN.RED.RED.GREEN.GREEN);
})->with('framebuffer drivers');

it('scales smoothly with the four source pixels around the sample point, edges held', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 4, 1);
    $buffer->paintImage(image(2, 1, '000000ff', WHITE), Affine::scaling(2.0, 1.0), filter: Filter::LINEAR);

    expect(bin2hex($buffer->dump()))->toBe('000000ff'.'404040ff'.'bfbfbfff'.'ffffffff');
})->with('framebuffer drivers');

it('weighs colours by their alpha when it smooths, so a clear pixel lends no colour', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 4, 1);
    $buffer->paintImage(image(2, 1, '00ff0000', 'ff0000ff'), Affine::scaling(2.0, 1.0), filter: Filter::LINEAR);

    // pure red at alpha 0, 64, 191, 255 over clear black: never a trace of the clear pixel's green
    expect(bin2hex($buffer->dump()))->toBe(TRANSPARENT.'40000040'.'bf0000bf'.'ff0000ff');
})->with('framebuffer drivers');

it('turns a source a quarter turn', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 2, 2);
    $buffer->paintImage(image(2, 1, RED, GREEN), new Affine(0.0, 1.0, -1.0, 0.0, 2.0, 0.0));

    expect(bin2hex($buffer->dump()))->toBe(TRANSPARENT.RED.TRANSPARENT.GREEN);
})->with('framebuffer drivers');

it('blends by source alpha and by opacity alike', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(FormatSpec::rgba8(), 3, 1);
    $buffer->fill(0xFFFFFFFF);
    $buffer->paintImage(image(1, 1, RED), Affine::identity(), 128);
    $buffer->paintImage(image(1, 1, 'ff000080'), Affine::translation(1.0, 0.0));
    $buffer->paintImage(image(1, 1, 'ff000000'), Affine::translation(2.0, 0.0));

    expect(bin2hex($buffer->dump()))->toBe('ff7f7fff'.'ff7f7fff'.WHITE);
})->with('framebuffer drivers');

it('thresholds on formats that cannot blend', function (FramebufferDriver $driver): void {
    $buffer = $driver->full(Formats::mono(), 8, 1);
    $buffer->paintImage(image(2, 1, 'ffffff7f', 'ffffff80'), Affine::identity());

    expect(bin2hex($buffer->dump()))->toBe('40');
})->with('framebuffer drivers');

it('keeps inside the clip and reports what it touched as damage', function (FramebufferDriver $driver): void {
    $buffer = $driver->dirty(FormatSpec::rgba8(), 4, 4);
    $buffer->beginEpoch();
    $buffer->paintImage(image(2, 2, RED, GREEN, BLUE, WHITE), Affine::translation(1.0, 1.0), clip: new Region(2, 0, 2, 4));

    expect(bin2hex($buffer->flushRegion(new Region(1, 1, 2, 2), FormatSpec::rgba8())))->toBe(TRANSPARENT.GREEN.TRANSPARENT.WHITE)
        ->and(array_map(fn (Region $r): array => [$r->x, $r->y, $r->width, $r->height], $buffer->damage()))->toBe([[2, 1, 1, 2]]);
})->with('framebuffer drivers');

it('paints nothing, and reports no damage, when nothing lands', function (FramebufferDriver $driver, Affine $placement, ?Region $clip): void {
    $buffer = $driver->dirty(FormatSpec::rgba8(), 4, 4);
    $buffer->beginEpoch();
    $buffer->paintImage(image(2, 2, RED, GREEN, BLUE, WHITE), $placement, clip: $clip);

    expect(bin2hex($buffer->dump()))->toBe(str_repeat(TRANSPARENT, 16))
        ->and($buffer->damage())->toBe([]);
})->with('framebuffer drivers')->with([
    'off the surface' => [Affine::translation(40.0, 0.0), null],
    'left of the surface' => [Affine::translation(-40.0, 0.0), null],
    'a flattened placement' => [Affine::scaling(0.0, 1.0), null],
    'a clip elsewhere' => [Affine::identity(), new Region(3, 3, 1, 1)],
    'a clip off the surface' => [Affine::identity(), new Region(10, 10, 4, 4)],
    'far beyond any surface' => [Affine::translation(1e300, -1e300), null],
]);

it('refuses an opacity outside 0..255', function (FramebufferDriver $driver, int $opacity): void {
    expect(fn () => $driver->full(FormatSpec::rgba8(), 2, 2)->paintImage(image(1, 1, RED), Affine::identity(), $opacity))
        ->toThrow(FramebufferException::class, 'paintImage() takes an opacity 0..255');
})->with('framebuffer drivers')->with([-1, 256]);

it('paints the back of a ring', function (FramebufferDriver $driver): void {
    $ring = $driver->ring(FormatSpec::rgba8(), 1, 1, 2);
    $ring->paintImage(image(1, 1, RED), Affine::identity());

    expect(bin2hex($ring->dump()))->toBe(TRANSPARENT)
        ->and(bin2hex($ring->present()->dump()))->toBe(RED);
})->with('framebuffer drivers');

it('paints the rows of the current page of a paged buffer', function (FramebufferDriver $driver): void {
    $paged = $driver->paged(FormatSpec::rgba8(), 1, 4, 2);
    $source = image(1, 4, RED, GREEN, BLUE, WHITE);

    $paged->setPage(1)->paintImage($source, Affine::identity());
    expect(bin2hex($paged->dump()))->toBe(BLUE.WHITE);

    $paged->setPage(0)->paintImage($source, Affine::identity());
    expect(bin2hex($paged->dump()))->toBe(RED.GREEN);

    $paged->setPage(1)->paintImage($source, Affine::translation(0.0, 1.0), clip: new Region(0, 3, 1, 1));
    expect(bin2hex($paged->dump()))->toBe(TRANSPARENT.BLUE);
})->with('framebuffer drivers');

it('takes the current page of a paged source, at its place', function (FramebufferDriver $driver): void {
    $source = $driver->paged(FormatSpec::rgba8(), 1, 4, 2);
    $source->setPage(1)->fill(0xFF0000FF);
    $buffer = $driver->full(FormatSpec::rgba8(), 1, 4);
    $buffer->paintImage($source, Affine::identity());

    expect(bin2hex($buffer->dump()))->toBe(TRANSPARENT.TRANSPARENT.RED.RED);
})->with('framebuffer drivers');

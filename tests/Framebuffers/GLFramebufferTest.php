<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\DamageGranularity;
use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferException;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\Framebuffers\Native\NativeDirtyFramebuffer;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\NutsAndBolts\Affine;

/*
 * A GPU engine's framebuffer, engine-neutral: FakeGLFramebuffer keeps the
 * target in a native RGBA8 framebuffer. Everything here is the abstract.
 */

function glTile(): Framebuffer
{
    $tile = new NativeFullFramebuffer(FormatSpec::rgba8(), 2, 2);
    $tile->fill(0xFF0000FF);

    return $tile;
}

function glMono(): FormatSpec
{
    return new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::MSB_FIRST);
}

/** A target with something in it: a grey ground, a white block, one red pixel. */
function glScene(): FakeGLFramebuffer
{
    $gl = new FakeGLFramebuffer(24, 8);
    $gl->pixels->fill(0x202020FF);
    $gl->pixels->setSegment(8, 2, 8, 4, 0xFFFFFFFF);
    $gl->pixels->setPixel(1, 1, 0xFF0000FF);

    return $gl;
}

dataset('pixel calls', [
    'setPixel' => [fn (Framebuffer $f) => $f->setPixel(3, 2, 0xFF0000FF)],
    'setPixels' => [fn (Framebuffer $f) => $f->setPixels([[1, 1, 0x00FF00FF], [6, 4, 0x0000FFFF]])],
    'setRegion' => [fn (Framebuffer $f) => $f->setRegion([[0, 0], [7, 5]], 0xFFFFFFFF)],
    'setSegment, clipped at the edge' => [fn (Framebuffer $f) => $f->setSegment(5, 3, 10, 10, 0x112233FF)],
    'paintSpans' => [fn (Framebuffer $f) => $f->paintSpans(pack('vvvC', 2, 1, 4, 255).pack('vvvC', 3, 1, 4, 128), 0xFF8000FF)],
    'paintImage' => [fn (Framebuffer $f) => $f->paintImage(glTile(), Affine::translation(2, 1))],
    'clear' => [fn (Framebuffer $f) => $f->clear()],
    'fill' => [fn (Framebuffer $f) => $f->fill(0x336699FF)],
    'blitFrom' => [fn (Framebuffer $f) => $f->blitFrom(glTile(), 1, 1)],
    'writeRgba8' => [fn (Framebuffer $f) => $f->writeRgba8(str_repeat("\x10\x20\x30\xff", 6), 3, 2, 4, 3)],
]);

it('is an RGBA8 damage-tracking framebuffer the size of its target, with its bytes on the GPU', function () {
    $gl = new FakeGLFramebuffer(24, 8);

    expect($gl)->toBeInstanceOf(DamageTrackingFramebuffer::class)
        ->and([$gl->viewportWidth(), $gl->viewportHeight()])->toBe([24, 8])
        ->and($gl->hostFormat())->toEqual(FormatSpec::rgba8())
        ->and($gl->damageGranularity())->toEqual(DamageGranularity::pixel(24, 8))
        ->and($gl->preservesContentsOnPresent())->toBeTrue()
        ->and($gl->pointer())->toBe(0)
        ->and($gl->damage())->toBe([]);
});

it('carries out every pixel call as a native framebuffer would, and records the same damage', function (Closure $call) {
    $gl = new FakeGLFramebuffer(8, 6);
    $gl->pixels->fill(0x202020FF);
    $native = new NativeDirtyFramebuffer(FormatSpec::rgba8(), 8, 6);
    $native->fill(0x202020FF);
    $native->beginEpoch();

    $call($gl);
    $call($native);

    expect($gl->toRgba8())->toBe($native->toRgba8())
        ->and($gl->damage())->toEqual($native->damage());
})->with('pixel calls');

it('uploads only what a call changed', function () {
    $gl = new FakeGLFramebuffer(8, 6);

    $gl->setPixel(3, 2, 0xFF0000FF);

    expect($gl->uploads)->toEqual([new Region(3, 2, 1, 1)])
        ->and($gl->pixels->getPixel(3, 2))->toBe(0xFF0000FF);
});

it('reads the target once for a run of pixel calls', function () {
    $gl = new FakeGLFramebuffer(8, 6);

    $gl->setPixel(1, 1, 0xFF0000FF)->setPixel(2, 2, 0x00FF00FF)->setSegment(4, 0, 2, 2, 0x0000FFFF);

    expect($gl->reads)->toEqual([new Region(0, 0, 8, 6)]);
});

it('reads the target again after the engine draws', function () {
    $gl = new FakeGLFramebuffer(8, 6);
    $gl->setPixel(1, 1, 0xFF0000FF);

    // The engine clears on the GPU, then says where it drew.
    $gl->pixels->fill(0x000080FF);
    $gl->drawn([new Region(0, 0, 8, 6)]);
    $gl->setPixel(2, 2, 0x00FF00FF);

    expect($gl->reads)->toHaveCount(2)
        ->and($gl->pixels->getPixel(1, 1))->toBe(0x000080FF)
        ->and($gl->pixels->getPixel(2, 2))->toBe(0x00FF00FF);
});

it('records where the engine drew, and starts a fresh record at beginEpoch()', function () {
    $gl = new FakeGLFramebuffer(24, 8);

    $gl->drawn([new Region(2, 2, 4, 4), new Region(16, 0, 4, 2)]);
    expect($gl->damage())->toEqual([new Region(2, 2, 4, 4), new Region(16, 0, 4, 2)]);

    expect($gl->beginEpoch()->damage())->toBe([]);
});

it('reads one pixel as its RGBA8 word', function () {
    expect(glScene()->getPixel(1, 1))->toBe(0xFF0000FF);
});

it('refuses a pixel outside the target', function () {
    glScene()->getPixel(24, 0);
})->throws(FramebufferException::class);

it('answers the whole target as RGBA8 from toRgba8(), dump() and flush()', function () {
    $gl = glScene();
    $bytes = $gl->pixels->toRgba8();

    expect($gl->toRgba8())->toBe($bytes)
        ->and($gl->dump())->toBe($bytes)
        ->and($gl->flush(FormatSpec::rgba8()))->toBe($bytes);
});

it('flushes a region into another format as a native framebuffer does', function (FormatSpec $spec, Region $region) {
    $gl = glScene();

    expect($gl->flushRegion($region, $spec))->toBe($gl->pixels->flushRegion($region, $spec))
        ->and($gl->flushRegion($region, $spec, true))->toBe($gl->pixels->flushRegion($region, $spec, true))
        ->and($gl->reads[0])->toEqual($region);
})->with([
    'RGB565, a region' => [rgb565(), new Region(8, 2, 8, 4)],
    'RGB565, the whole target' => [rgb565(), new Region(0, 0, 24, 8)],
    '1-bit, whole bytes across' => [glMono(), new Region(8, 2, 16, 4)],
    'RGBA8, a region' => [FormatSpec::rgba8(), new Region(1, 1, 3, 3)],
]);

it('refuses to flush a region outside the target', function () {
    glScene()->flushRegion(new Region(20, 0, 8, 8), FormatSpec::rgba8());
})->throws(FramebufferException::class);

it('copies itself into another framebuffer', function () {
    $gl = glScene();
    $copy = new NativeFullFramebuffer(FormatSpec::rgba8(), 24, 8);

    $gl->blitTo($copy);

    expect($copy->toRgba8())->toBe($gl->pixels->toRgba8());
});

it('brings a region of its staging copy up to date, in the staging copy\'s format', function () {
    $gl = glScene();
    $staging = new NativeFullFramebuffer(rgb565(), 24, 8);
    $gl->stageIn($staging);

    $answered = $gl->stage(new Region(8, 2, 8, 4));

    expect($answered)->toBe($staging)
        ->and($staging->flushRegion(new Region(8, 2, 8, 4), rgb565()))->toBe($gl->pixels->flushRegion(new Region(8, 2, 8, 4), rgb565()))
        ->and($staging->getPixel(1, 1))->toBe(0);
});

it('answers its staging copy\'s address from pointer()', function () {
    $gl = glScene();
    $staging = framebuffers()->driver('extended')->full(rgb565(), 24, 8);

    expect($gl->stageIn($staging)->pointer())->toBe($staging->pointer())
        ->and($gl->pointer())->not->toBe(0)
        ->and($gl->stageIn(null)->pointer())->toBe(0);
})->skip(fn () => ! class_exists(FbBuffer::class), 'needs ext-fb');

it('refuses a staging copy of another size', function () {
    glScene()->stageIn(new NativeFullFramebuffer(rgb565(), 16, 8));
})->throws(FramebufferException::class, 'A staging copy is the size of its target: 24 × 8, got 16 × 8.');

it('refuses to stage without a staging copy', function () {
    glScene()->stage(new Region(0, 0, 4, 4));
})->throws(FramebufferException::class, 'No staging copy is set: stageIn() one first.');

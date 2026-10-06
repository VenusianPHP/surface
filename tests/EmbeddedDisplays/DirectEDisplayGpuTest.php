<?php

declare(strict_types=1);

use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\BitOrder;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Framebuffers\ScanDirection;
use Surface\EmbeddedDisplays\DirectEDisplay;

/*
 * A GPU engine's framebuffer on a display that pipes: the display gives it an
 * ext-fb staging copy in the panel's format, brings each region up to date
 * before sending it, and pipes from the staging copy's memory.
 */

function gpuDirect(?FakePipePanel $panel = null): DirectEDisplay
{
    return new DirectEDisplay('tft', $panel ?? new FakePipePanel(16, 8, rgb565()), framebuffers(), ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full']);
}

/** A 16 x 8 target with a white block on grey. */
function gpuTarget(): FakeGLFramebuffer
{
    $gl = new FakeGLFramebuffer(16, 8);
    $gl->pixels->fill(0x404040FF);
    $gl->pixels->setSegment(4, 2, 6, 3, 0xFFFFFFFF);

    return $gl;
}

/**
 * The bytes at $spans, read out of the staging copy: dump() copies its memory, so an address is an offset from pointer().
 *
 * @param  list<array{int, int}>  $spans
 */
function stagedBytes(FakeGLFramebuffer $gl, array $spans): string
{
    $staging = $gl->stage(new Region(0, 0, 1, 1));
    $memory = $staging->dump();

    return implode('', array_map(fn (array $span): string => substr($memory, $span[0] - $staging->pointer(), $span[1]), $spans));
}

beforeEach(function () {
    if (! class_exists(FbBuffer::class)) {
        $this->markTestSkipped('needs ext-fb');
    }
});

it('gives a GPU framebuffer a staging copy in C memory when it is bound', function () {
    $display = gpuDirect();
    $gl = gpuTarget();

    expect($gl->pointer())->toBe(0)
        ->and($display->canPipe($gl))->toBeTrue();

    $display->bind($gl);

    expect($display->boundFramebuffer())->toBe($gl)
        ->and($gl->pointer())->not->toBe(0);
});

it('pipes the whole frame first, out of the staging copy, in the panel\'s format', function () {
    $display = gpuDirect();
    $gl = gpuTarget();
    $display->bind($gl);

    $display->present();

    expect($display->panel()->calls)->toBe([['window', 0, 0, 16, 8]])
        ->and($display->panel()->bus->spans)->toBe([[[$gl->pointer(), 16 * 8 * 2]]])
        ->and(stagedBytes($gl, $display->panel()->bus->spans[0]))->toBe($gl->flush(rgb565()))
        ->and($gl->damage())->toBe([]);
});

it('pipes only what the engine drew afterwards, a span a row', function () {
    $display = gpuDirect();
    $gl = gpuTarget();
    $display->bind($gl);
    $display->present();

    $gl->pixels->setSegment(10, 4, 4, 2, 0xFF0000FF);
    $gl->drawn([new Region(10, 4, 4, 2)]);
    $display->present();

    $spans = $display->panel()->bus->spans[1];
    expect($display->panel()->calls[1])->toBe(['window', 10, 4, 4, 2])
        ->and($spans)->toBe([[$gl->pointer() + (4 * 16 + 10) * 2, 8], [$gl->pointer() + (5 * 16 + 10) * 2, 8]])
        ->and(stagedBytes($gl, $spans))->toBe($gl->flushRegion(new Region(10, 4, 4, 2), rgb565()));
});

it('sends nothing when the engine drew nothing', function () {
    $display = gpuDirect();
    $gl = gpuTarget();
    $display->bind($gl);
    $display->present();

    $display->present();

    expect($display->panel()->bus->spans)->toHaveCount(1);
});

it('refuses a GPU framebuffer for a panel it cannot pipe to, and leaves it unstaged', function () {
    $mono = new FormatSpec(PixelFormat::MONO_HORIZONTAL, BitDepth::B1, ScanDirection::TOP_TO_BOTTOM, BitOrder::MSB_FIRST);
    $display = gpuDirect(new FakePipePanel(16, 8, $mono));
    $gl = gpuTarget();

    expect($display->canPipe($gl))->toBeFalse();
    expect(fn () => $display->bind($gl))->toThrow(EmbeddedDisplayException::class, 'the panel packs pixels into part of a byte, or into planes');
    expect($gl->pointer())->toBe(0)
        ->and($display->boundFramebuffer())->toBeNull();
});

it('refuses a GPU framebuffer of another size, and leaves it unstaged', function () {
    $display = gpuDirect();
    $gl = new FakeGLFramebuffer(32, 8);

    expect(fn () => $display->bind($gl))->toThrow(EmbeddedDisplayException::class);
    expect($gl->pointer())->toBe(0);
});

it('refuses to pipe in the old format once the panel\'s format has changed', function () {
    $panel = new FakePipePanel(16, 8, rgb565());
    $display = gpuDirect($panel);
    $gl = gpuTarget();
    $display->bind($gl);
    $display->present();

    $panel->format = FormatSpec::rgba8();
    $gl->drawn([new Region(0, 0, 4, 4)]);

    $display->present();
})->throws(EmbeddedDisplayException::class);

it('stays unbound when the staging copy cannot be made', function () {
    $display = new class('tft', new FakePipePanel(16, 8, rgb565()), framebuffers(), ['refreshing' => 'epaper', 'addressable' => 'dirty', 'whole' => 'full']) extends DirectEDisplay {
        protected function stagingCopy(): Surface\Contracts\Framebuffers\Framebuffer
        {
            throw new RuntimeException('no ext-fb');
        }
    };
    $gl = gpuTarget();

    expect(fn () => $display->bind($gl))->toThrow(RuntimeException::class, 'no ext-fb');
    expect($display->boundFramebuffer())->toBeNull()
        ->and($gl->pointer())->toBe(0);
});

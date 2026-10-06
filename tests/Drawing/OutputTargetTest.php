<?php

use Surface\Contracts\Drawing\OutputTarget;
use Surface\Contracts\Drawing\Pipeable;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Contracts\Windows\Primitives\TKCanvas;

it('makes a toolkit canvas a drawing output', function () {
    expect(is_subclass_of(TKCanvas::class, OutputTarget::class))->toBeTrue();
});

it('makes a target say its framebuffer, and a pipeable target say what it can pipe', function () {
    expect(method_exists(OutputTarget::class, 'framebuffer'))->toBeTrue()
        ->and(is_subclass_of(Pipeable::class, OutputTarget::class))->toBeTrue()
        ->and(is_subclass_of(TKCanvas::class, Pipeable::class))->toBeTrue()
        ->and(is_subclass_of(EmbeddedDisplay::class, OutputTarget::class))->toBeTrue()
        ->and(is_subclass_of(EmbeddedDisplay::class, Pipeable::class))->toBeFalse();
});
it('makes every target say its size in pixels and the format it shows', function () {
    $display = fakeDisplay();
    $canvas = (new Venusian\Surface\Tests\Fixtures\FakeHost('main'))->column('m')->canvas('view');

    expect($display->pixelSize())->toBe([16, 8])
        ->and($display->pixelFormat())->toEqual(rgb565())
        ->and($canvas->pixelSize())->toBe([80, 60])
        ->and($canvas->pixelFormat())->toEqual(Surface\Contracts\Framebuffers\FormatSpec::rgba8());
});

it('makes a toolkit canvas a window output', function () {
    expect(is_subclass_of(TKCanvas::class, Surface\Contracts\Drawing\WindowOutput::class))->toBeTrue()
        ->and(is_subclass_of(Surface\Contracts\Drawing\WindowOutput::class, Pipeable::class))->toBeTrue()
        ->and(is_subclass_of(EmbeddedDisplay::class, Surface\Contracts\Drawing\WindowOutput::class))->toBeFalse();
});

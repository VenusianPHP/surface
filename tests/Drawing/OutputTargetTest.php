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
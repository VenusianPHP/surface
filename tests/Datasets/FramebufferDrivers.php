<?php

declare(strict_types=1);

use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Surface\Framebuffers\Native\NativeFramebufferDriver;

/** Every framebuffer driver this PHP can run: native always, extended when ext-fb 0.10 is loaded. */
dataset('framebuffer drivers', function () {
    yield 'native' => [new NativeFramebufferDriver()];

    if (class_exists(FbBuffer::class)) {
        yield 'extended' => [new ExtendedFramebufferDriver()];
    }
});

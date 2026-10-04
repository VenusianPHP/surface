<?php

declare(strict_types=1);

use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Surface\Images\Extended\ExtendedImageDecoder;
use Surface\Images\Native\NativeImageDecoder;

/** Every images driver this PHP can run: native always (its PNG and JPEG need ext-gd), extended when ext-imgdec is loaded. */
dataset('image decoders', function () {
    yield 'native' => [new NativeImageDecoder(new NativeFramebufferDriver())];

    if (function_exists('imgdec_png')) {
        yield 'extended' => [new ExtendedImageDecoder(new NativeFramebufferDriver())];
    }
});

<?php

namespace Surface\Framebuffers\Php;

use Surface\Contracts\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\MultiFrameFramebuffer;
use Surface\Contracts\Framebuffers\PagedFramebuffer;

/** The in-house driver: bytes in PHP strings. Always available. */
class PhpFramebufferDriver implements FramebufferDriver
{
    public function driver(): string
    {
        return 'php';
    }

    public function full(FormatSpec $format, int $width, int $height): Framebuffer
    {
        return new FullFramebuffer($format, $width, $height);
    }

    public function dirty(FormatSpec $format, int $width, int $height): DamageTrackingFramebuffer
    {
        throw new \LogicException('Task 4');
    }

    public function epaper(FormatSpec $format, int $width, int $height): Framebuffer
    {
        throw new \LogicException('Task 4');
    }

    public function paged(FormatSpec $format, int $width, int $height, int $page_rows): PagedFramebuffer
    {
        throw new \LogicException('Task 4');
    }

    public function ring(FormatSpec $format, int $width, int $height, int $frames): MultiFrameFramebuffer
    {
        throw new \LogicException('Task 4');
    }
}

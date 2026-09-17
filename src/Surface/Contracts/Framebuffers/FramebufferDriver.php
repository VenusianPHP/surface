<?php

namespace Surface\Contracts\Framebuffers;

/** Where the bytes live. Mints the five buffer kinds; engines never name a buffer class. */
interface FramebufferDriver
{
    /** 'php' or 'native'. */
    public function driver(): string;

    public function full(FormatSpec $format, int $width, int $height): Framebuffer;

    public function dirty(FormatSpec $format, int $width, int $height): DamageTrackingFramebuffer;

    public function epaper(FormatSpec $format, int $width, int $height): Framebuffer;

    public function paged(FormatSpec $format, int $width, int $height, int $page_rows): PagedFramebuffer;

    public function ring(FormatSpec $format, int $width, int $height, int $frames): MultiFrameFramebuffer;
}

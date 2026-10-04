<?php

namespace Surface\Contracts\Framebuffers;

/** Where the bytes live. Mints the five buffer kinds; callers never name a buffer class. */
interface FramebufferDriver
{
    /** 'native' (PHP holds the bytes) or 'extended' (ext-fb holds them). */
    public function driver(): string;

    public function full(FormatSpec $format, int $width, int $height): Framebuffer;

    public function dirty(FormatSpec $format, int $width, int $height): DamageTrackingFramebuffer;

    public function epaper(FormatSpec $format, int $width, int $height): ePaperFramebuffer;

    public function paged(FormatSpec $format, int $width, int $height, int $page_rows): PagedFramebuffer;

    public function ring(FormatSpec $format, int $width, int $height, int $frames): RingFramebuffer;
}

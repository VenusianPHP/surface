<?php

namespace Surface\Contracts\Framebuffers;

/**
 * One colour plane (planar) or one palette entry (packed index).
 *
 * $color is an EInkColor value. $inverted: on a planar host this channel packs
 * its colour as 0 and the background as 1 (SSD1680 black-RAM convention).
 * $code: the panel's wire code on a packed-index host; null means the
 * channel's position in its palette. Ints and bools only — no enum imports.
 */
readonly class ChannelSpec
{
    public function __construct(
        public int $color,
        public bool $inverted = false,
        public ?int $code = null,
    ) {}
}

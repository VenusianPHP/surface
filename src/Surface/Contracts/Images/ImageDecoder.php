<?php

namespace Surface\Contracts\Images;

use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * Encoded image bytes into a framebuffer. Every driver reads PNG, JPEG and
 * TIFF into straight (not premultiplied) RGBA8 by the same rules:
 *
 * - PNG: palettes and grey expanded, tRNS made alpha, 16-bit samples cut to
 *   their high byte, interlacing undone, no gamma applied.
 * - JPEG: libjpeg's default decode; grey made RGB; CMYK as gd converts it
 *   (inverted when an Adobe marker says so); alpha 255.
 * - TIFF (classic, not BigTIFF): the first image, chunky or in separate
 *   planes, top-left; grey of either polarity at 1, 2, 4, 8 or 16 bits, RGB
 *   at 8 or 16, a palette at 1, 2, 4 or 8 (colour map entries cut to their
 *   high byte); unsigned samples; a first extra sample that is alpha is kept (associated alpha
 *   divided out: c = round(c × 255 ÷ a), capped at 255).
 *
 * The 'native' driver reads PNG and JPEG through ext-gd, which holds alpha
 * in 7 bits: a PNG alpha a comes back as 255 − (2·q + q ≫ 6) with
 * q = 127 − (a ≫ 1), so 0 and 255 survive and a partly transparent pixel can
 * sit one step off. gd hands back 16-bit samples already cut, so a 16-bit
 * grey or RGB tRNS colour is matched on its high bytes. TIFF it reads in PHP:
 * compression none, PackBits, LZW or Deflate, predictor 1 or 2 (under LZW and
 * Deflate, the codecs it belongs to, as libtiff reads it). The
 * 'extended' driver (ext-imgdec) keeps every alpha bit, matches tRNS on all
 * 16 bits, and reads any compression libtiff does.
 */
interface ImageDecoder
{
    /** The longest side an image may have: a framebuffer's. */
    public const int MAX_SIDE = 65535;

    /** The most pixels one image may hold: 8192 × 8192. */
    public const int MAX_PIXELS = 1 << 26;

    /** 'native' (ext-gd and PHP) or 'extended' (ext-imgdec). */
    public function driver(): string;

    /**
     * A full RGBA8 framebuffer of the image, on the framebuffer driver this
     * decoder was given.
     *
     * @throws ImageException When the bytes are not a readable PNG, JPEG or
     *   TIFF, use a layout the rules above leave out, or are past MAX_SIDE or
     *   MAX_PIXELS.
     */
    public function decode(string $bytes): Framebuffer;
}

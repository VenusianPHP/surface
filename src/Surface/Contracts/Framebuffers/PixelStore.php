<?php

namespace Surface\Contracts\Framebuffers;

/**
 * The pixel glob: one surface's bytes in one FormatSpec at one size, and every
 * operation that reads or writes them. A framebuffer kind decides which pixels
 * to touch and what that means (damage, pages, frames); a store only holds
 * them. `native` stores keep the bytes in a PHP string, `extended` stores keep
 * them in C through ext-fb.
 *
 * Coordinates are the store's own. A coordinate outside the store throws
 * FramebufferException; a list with any pixel outside is refused whole, with
 * nothing written.
 */
interface PixelStore
{
    public function width(): int;

    public function height(): int;

    public function format(): FormatSpec;

    public function get(int $x, int $y): int;

    public function set(int $x, int $y, int $value): void;

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $pixels  [x, y, value]
     * @return Region|null The bounding box written; null for an empty list.
     */
    public function setPixels(array $pixels): ?Region;

    /**
     * @param  array<int, array{0: int, 1: int}>  $coordinates  [x, y]
     * @return Region|null The bounding box written; null for an empty list.
     */
    public function setCoordinates(array $coordinates, int $value): ?Region;

    /** Fill a rect that lies inside the store. */
    public function rect(Region $region, int $value): void;

    public function fill(int $value): void;

    /** The raw bytes, top row first; one plane of a planar store when $layer is given. */
    public function bytes(?int $layer = null): string;

    /** A sub-rect's bytes in $spec, rows in $spec's scan direction. */
    public function region(Region $region, FormatSpec $spec): string;

    /** RGBA8 bytes, top-left first, the store's size. */
    public function toRgba8(): string;

    /**
     * Map RGBA8 pixels into the store at an offset, clipped to the store and to $clip.
     *
     * @return Region|null What was written; null when nothing landed.
     */
    public function blitRgba8(string $rgba8, int $width, int $height, int $offset_x, int $offset_y, ?Region $clip = null): ?Region;

    /**
     * Paint coverage spans (Spans) in one 0xRRGGBBAA colour, by Framebuffer::paintSpans()'s rules.
     *
     * @return Region|null The spans' bounding box; null for an empty list.
     */
    public function paintSpans(string $spans, int $rgba8): ?Region;

    /** Copy a rect from a store of the same size and format, words as stored. */
    public function copy(PixelStore $source, Region $region): void;

    /** One bit per pixel, rows padded to a byte, x0 in bit 7: set where the pixel's word equals $value. */
    public function plane(int $value): string;

    /** Native address of the bytes; 0 when PHP owns them. */
    public function pointer(): int;

    public function granularity(): DamageGranularity;
}

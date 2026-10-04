<?php

namespace Surface\Contracts\Framebuffers;

use Surface\NutsAndBolts\Affine;

interface Framebuffer
{
    public function viewportWidth(): int;

    public function viewportHeight(): int;

    public function hostFormat(): FormatSpec;

    public function getPixel(int $x, int $y): int;

    public function setPixel(int $x, int $y, int $value): static;

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $pixels  Each entry is [x, y, color]
     */
    public function setPixels(array $pixels): static;

    /**
     * @param  array<int, array{0: int, 1: int}>  $coordinates
     */
    public function setRegion(array $coordinates, int $value): static;

    public function setSegment(int $x, int $y, int $width, int $height, int $color): static;

    /**
     * Paint coverage spans (Spans) in one 0xRRGGBBAA colour: RGB and grey
     * formats blend source-over by alpha × coverage; mono, palette and planar
     * formats write the colour where that reaches 128 and nothing below. The
     * whole list is checked first; a span empty or outside the surface throws
     * and nothing is written.
     */
    public function paintSpans(string $spans, int $rgba8): static;

    /**
     * Paint another framebuffer's pixels through $placement, which maps the
     * source's pixel coordinates onto this surface: scaled, turned, moved. A
     * pixel here is painted when its centre maps inside the source, from the
     * source pixel under that point (NEAREST) or the four around it (LINEAR,
     * edges held), blended like paintSpans() with source alpha × $opacity
     * (0..255) as the alpha. Pixels outside the surface or $clip are skipped.
     * A paged source gives its current page, at its place.
     */
    public function paintImage(Framebuffer $source, Affine $placement, int $opacity = 255, Filter $filter = Filter::NEAREST, ?Region $clip = null): static;

    public function clear(): static;

    public function fill(int $color): static;

    public function blitTo(Framebuffer $target, int $offset_x = 0, int $offset_y = 0): Framebuffer;

    public function blitFrom(Framebuffer $source, int $offset_x = 0, int $offset_y = 0): Framebuffer;

    /**
     * Put $width × $height RGBA8 pixels (top-left first) on the surface with
     * their top-left at ($x, $y): each replaces the pixel there, mapped into
     * the host format; what falls off the surface is dropped. A paged buffer
     * takes surface coordinates and keeps the rows on its current page. Bytes
     * other than $width × $height × 4 throw and nothing is written.
     */
    public function writeRgba8(string $rgba8, int $width, int $height, int $x = 0, int $y = 0): static;

    /**
     * Raw host bytes (optional layer).
     */
    public function dump(?int $layer = null): string;

    /**
     * Emit pixels in the requested FormatSpec.
     *
     * @return string|array<int, mixed>
     */
    public function flush(FormatSpec $spec, bool $as_array = false): string|array;

    /**
     * Bytes of one sub-rect in the requested FormatSpec. $region must already be
     * snapped to damageGranularity(); a region that is not is a caller bug.
     *
     * @return string|list<int>
     */
    public function flushRegion(Region $region, FormatSpec $spec, bool $as_array = false): string|array;

    /** RGBA8 bytes, top-left first, viewport size. */
    public function toRgba8(): string;

    /** Native address of the bytes; 0 when PHP owns them. */
    public function pointer(): int;

    public function damageGranularity(): DamageGranularity;

    /**
     * True when the logical canvas still holds the previous frame after a present.
     */
    public function preservesContentsOnPresent(): bool;
}
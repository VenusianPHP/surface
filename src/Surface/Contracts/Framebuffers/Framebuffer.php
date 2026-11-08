<?php

namespace Surface\Contracts\Framebuffers;

use Surface\Contracts\Drawing\Effects\FilterChain;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\GradientSpread;

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
     * The native buffer a rasterizer may paint spans straight into, as paintSpans() would (ext-fb's FbBuffer), or
     * null when spans must go through paintSpans(). Whoever paints into it reports what it touched with paintedSpans().
     */
    public function spanSink(): ?object;

    /**
     * Glass (drawing-path spec G4), on RGBA8 scratch frames of one driver: this frame, ceil(w / 2) x ceil(h / 2), as one
     * dual-filter (Kawase) level down from the region of $source. See PixelStore::kawaseDown().
     */
    public function kawaseDown(Framebuffer $source, Region $region): static;

    /** One level up: this RGBA8 frame, at most twice $source on each side, from it by the 8-tap tent. */
    public function kawaseUp(Framebuffer $source): static;

    /**
     * A glass pane blended into this framebuffer inside $clip, from a blurred level ($scale pixels a pixel, from
     * ($originX, $originY)) and the sharp capture, RGBA8 frames of the same driver. See PixelStore::paintGlass().
     *
     * @param  array{float, float, float, float, array{float, float, float, float}}  $geometry  x0, y0, x1, y1, radii
     * @param  list<float|int>  $look  [frost, refraction, bevel, saturation, light, grain, tint, rim, alpha]
     */
    public function paintGlass(array $geometry, Framebuffer $blur, int $scale, ?Framebuffer $sharp, int $originX, int $originY, array $look, Region $clip): static;

    /**
     * Records what spans painted into spanSink() touched, as paintSpans() records what it paints: one region a
     * span list, null for one that painted nothing.
     *
     * @param  list<Region|null>  $regions
     */
    public function paintedSpans(array $regions): static;

    /**
     * Paint coverage masks in one 0xRRGGBBAA colour, in list order, each cut to
     * $clip. A mask is [string $coverage, int $width, int $left, int $top]: rows
     * of $width coverage bytes 0..255, top-down, its top-left pixel at ($left,
     * $top), which may lie off the surface (within ±2^30). Every pixel of
     * coverage above 0 is painted as a span of that coverage is by
     * paintSpans(). The whole list is checked first; a bad mask, or a clip
     * empty or outside the surface, throws and nothing is written.
     *
     * @param  list<array{string, int, int, int}>  $masks
     */
    public function paintMasks(array $masks, int $rgba8, Region $clip): static;

    /**
     * Paint another framebuffer's pixels through $placement, which maps the
     * source's pixel coordinates onto this surface: scaled, turned, moved. A
     * pixel here is painted when its centre maps inside the source, from the
     * source pixel under that point (NEAREST) or the four around it (LINEAR,
     * edges held), blended like paintSpans() with source alpha × $opacity
     * (0..255) as the alpha. Pixels outside the surface or $clip are skipped.
     * A paged source gives its current page, at its place.
     *
     * Three optional parts change what is painted, applied in this order to each
     * sampled pixel (all in whole 0..255 steps, each rounded to nearest, halves up):
     *  1. $tint, 0xRRGGBBAA, is multiplied in per channel: c = (c × t + 127) / 255,
     *     alpha included, so a tint's alpha is an opacity. 0xFFFFFFFF changes nothing.
     *  2. $opacity scales the alpha, as before.
     *  3. $coverage, Spans in this surface's coordinates, scales the alpha by the
     *     coverage of the span that holds the pixel; a pixel no span holds is skipped.
     * $window, [u0, v0, u1, v1] with u0 < u1 and v0 < v1 in the placement's source
     * coordinates (a paged source's rows are offset as the placement is), limits
     * the painted pixels to those whose centre maps inside it. The window crops
     * only: smooth sampling still reads, and holds at the edges of, the whole source.
     */
    public function paintImage(Framebuffer $source, Affine $placement, int $opacity = 255, Filter $filter = Filter::NEAREST, ?Region $clip = null, int $tint = 0xFFFFFFFF, ?array $window = null, ?string $coverage = null): static;

    /**
     * Paints a gradient through spans: each covered pixel takes the ramp colour of its position, its alpha scaled by the
     * span's coverage, blended over what is there. The position of a pixel centre (x + ½, y + ½) goes through $inverse
     * (a, b, c, d, e, f: u = a·x + c·y + e, v = b·x + d·y + f); a linear gradient's t is u, a radial one's is hypot(u, v);
     * $spread folds t into 0..1; the ramp entry is floor(t × 255 + ½). Spans must lie inside the surface.
     *
     * @param  string  $spans  Packed spans, in rows ascending, x ascending without overlap within a row.
     * @param  string  $ramp  256 colours of 4 bytes, RGBA, straight alpha.
     * @param  string  $kind  'linear' or 'radial'.
     * @param  array{float, float, float, float, float, float}  $inverse
     */
    public function paintGradient(string $spans, string $ramp, string $kind, GradientSpread $spread, array $inverse): static;

    /**
     * Replaces the pixels of $region (null: the whole surface) with the result of a filter chain run over them. The
     * chain's `source` is those pixels as they are (straight RGBA8; the steps work on premultiplied values and the result
     * is straightened again); a blur, a displacement or a flood reads and writes only inside the region, and noise is a
     * function of the position on the surface, so the same chain over the same pixels gives the same bytes at any place
     * and on every driver. A paged framebuffer takes a region inside its current page.
     *
     * With $premultiplied the region's pixels are premultiplied RGBA8, as a layer drawn over nothing leaves them: they
     * are the chain's source as they are, and the result is still written straight. An empty chain then only straightens.
     */
    public function filter(FilterChain $chain, ?Region $region = null, bool $premultiplied = false): static;

    /**
     * A new framebuffer of $width x $height pixels, blank, in RGBA8, of this framebuffer's driver (native stays native,
     * extended stays in C): somewhere to draw a layer before it lands.
     */
    public function scratch(int $width, int $height): Framebuffer;

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
     * Move the pixels of $region, clipped to the surface, by ($dx, $dy): pixels that move past
     * the region's edge are dropped, pixels outside it do not change, and the exposed strip keeps
     * its old pixels until drawn. Damage, where tracked, is the whole clipped region. A read-only
     * framebuffer throws as for any write; a paged one, holding one page at a time, throws.
     */
    public function scroll(Region $region, int $dx, int $dy): void;

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
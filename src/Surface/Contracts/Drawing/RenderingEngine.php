<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\FillRule;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;

/**
 * Something that draws into a framebuffer: the software engine on the CPU, or
 * a graphics library on the GPU. One API for all of them.
 *
 * Drawing happens in frames. Between begin() and end() the draw calls are
 * recorded, in order, already transformed; end() draws them into the engine's
 * framebuffer. Getting the pixels out is the framebuffer's job, through
 * framebuffer(): the engine draws, the framebuffer drains.
 *
 * Coordinates are float pixels of the framebuffer, integer coordinates on
 * pixel edges, under the current transform. Points are [x, y] pairs. A zero
 * or negative size, radius or stroke draws nothing. A number that is not
 * finite, or lands past LIMIT once transformed, throws DrawingException, as
 * does a draw or state call outside a frame.
 */
interface RenderingEngine
{
    /** 2²⁴: the furthest a transformed coordinate may land. */
    public const float LIMIT = 16777216.0;

    public function name(): string;

    /** Where this engine draws: the one it was given, or the one its graphics library made. */
    public function framebuffer(): Framebuffer;

    public function width(): int;

    public function height(): int;

    /** Start a frame: identity transform, no clip, nothing recorded. */
    public function begin(): static;

    /** Draw what was recorded into the framebuffer, and keep it for replay(). */
    public function end(): static;

    /**
     * begin(), $draw($this), end(). If $draw throws, the frame is dropped: nothing is drawn and the last frame stays the one replay() repeats.
     *
     * @param  callable(RenderingEngine): void  $draw
     */
    public function frame(callable $draw): static;

    /** Draw the last ended frame again, into the framebuffer as it stands now. */
    public function replay(): static;

    /** True between begin() and end(). */
    public function drawing(): bool;

    /** Remember the transform and the clip. */
    public function push(): static;

    /** Return to the transform and clip of the matching push(). */
    public function pop(): static;

    public function translate(float $x, float $y): static;

    /** $y defaults to $x. */
    public function scale(float $x, ?float $y = null): static;

    /** Turns +x towards +y: clockwise on screen. */
    public function rotate(float $radians): static;

    /** Apply $matrix inside the current transform: what is drawn next goes through $matrix first. */
    public function transform(Affine $matrix): static;

    /** The current transform; identity outside a frame. */
    public function matrix(): Affine;

    /** Keep drawing inside $region, in framebuffer pixels whatever the transform; null lifts it. */
    public function clip(?Region $region): static;

    /** The clip as it applies: inside the surface, empty when nothing can be drawn. The whole surface when none is set. */
    public function clipRegion(): Region;

    /** Replace every pixel of the surface with $color, whatever the clip. */
    public function clear(Color $color): static;

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): static;

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): static;

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): static;

    /** @param list<array{float, float}> $points */
    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): static;

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): static;

    /** @param list<array{float, float}> $points */
    public function fillPolygon(array $points, Color $color, FillRule $rule = FillRule::NON_ZERO): static;

    /** @param list<list<array{float, float}>> $contours */
    public function fillPath(array $contours, Color $color, FillRule $rule = FillRule::NON_ZERO): static;

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): static;

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, Color $color, float $stroke = 1.0): static;

    /**
     * Draw another framebuffer's pixels with their top-left at (x, y), at the
     * source's own size unless a width and height are given. The source is
     * read when the frame is drawn, not when this is called.
     *
     * @param  float  $opacity  0..1
     */
    public function image(Framebuffer $source, float $x, float $y, ?float $width = null, ?float $height = null, float $opacity = 1.0, Filter $filter = Filter::NEAREST): static;
}

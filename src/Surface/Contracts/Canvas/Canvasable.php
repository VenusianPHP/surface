<?php

namespace Surface\Contracts\Canvas;

use Surface\Contracts\Drawing\Color;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\DrawTarget;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\GPUEngine;

/**
 * One lifecycle over whatever a sketch draws into: a GPU view, a GPU or CPU
 * stage, an embedded display. Show it, hide it, size it, close it, ask it for
 * a frame — the same calls whatever is behind it.
 *
 * Where the pixels go and what makes them are two choices: output() is the
 * first, engine() the second, and neither constrains the other.
 *
 * It is not a Drawing2D. draw() hands the sketch the rasteriser's own drawer
 * inside the frame, which is the one place ink is legal and the only way the
 * same sketch behaves on every output. Type-hint this; ask output() for the
 * concrete only for what is specific to its kind (window mail, a panel).
 */
interface Canvasable
{
    public function output(): DrawTarget;

    public function kind(): CanvasKind;

    /**
     * Rasterise through this engine from here on: any CPU engine (dirty, full,
     * epaper, paged, nframes) or any GPU engine (metal, opengl, vulkan, sdl3),
     * on any output. A GPU engine needs no window — it attaches to nothing and
     * its frame is read back — so a panel may be drawn by Metal and a Metal
     * window by a CPU engine. The output keeps its size, format and lifecycle.
     */
    public function engine(GPUEngine|CPUEngine|string $engine): static;

    /**
     * Which engine is drawing. Not the same question as output()->engine():
     * a panel rasterised by Metal still answers a CPU engine there, because
     * what it holds is finished pixels.
     */
    public function rasteriser(): GPUEngine|CPUEngine;

    /** The rasteriser's drawer, for work that is not ink — minting a texture, measuring text. */
    public function drawing(): Drawing2D;

    /** The per-frame hook: fn(Drawing2D $g, Frame $frame): void. Replace, not stack. Asks for no frames by itself. */
    public function draw(callable $hook): static;

    /** One frame on the next tick. */
    public function present(): static;

    /** Continuous frames. Off by default: a Canvas draws when present() asks. */
    public function animate(bool $on = true): static;

    /**
     * What the output clears to. A per-frame target (GPU, nframes, paged)
     * applies it before every hook; a target that keeps its pixels applies it
     * at attach and on clear().
     */
    public function background(Color $color): static;

    public function width(): int;

    public function height(): int;

    /** @return array{int, int} */
    public function size(): array;

    public function show(): static;

    /** Throws CanvasException when this kind cannot hide. */
    public function hide(): static;

    public function isVisible(): bool;

    /** Terminal: the view is removed, the stage closed, the display detached. Verbs throw after. */
    public function close(): void;

    public function isOpen(): bool;
}

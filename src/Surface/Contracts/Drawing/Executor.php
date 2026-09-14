<?php

namespace Surface\Contracts\Drawing;

use Surface\Contracts\NativeWindows\Views\Color;

/**
 * The intersection every engine executes today. An engine package implements
 * this over its own binding; the Painter speaks only this.
 *
 * Sizes and coordinates are PIXELS. Vertices are `x y z r g b a u v` packed
 * `g9`; indices are `v*` uint16. The Transform is the projection.
 */
interface Executor
{
    public function capabilities(): ExecutorCapabilities;

    public function resize(int $width, int $height): void;

    /** @return array{int, int} */
    public function drawableSize(): array;

    /** False means no drawable this tick — the caller skips its hook. */
    public function beginFrame(Color $clear): bool;

    public function viewport(int $x, int $y, int $width, int $height): void;

    public function scissor(int $x, int $y, int $width, int $height): void;

    public function unscissor(): void;

    public function texture(string $rgba8, int $width, int $height): TextureHandle;

    public function releaseTexture(TextureHandle $texture): void;

    public function draw(Topology $topology, string $vertices, int $vertex_count, Transform $transform, ?TextureHandle $texture = null, int $instances = 1): void;

    public function drawIndexed(Topology $topology, string $vertices, int $vertex_count, string $indices, int $index_count, Transform $transform, ?TextureHandle $texture = null, int $instances = 1): void;

    /**
     * RGBA8 bytes at drawable size of what has been drawn so far. Legal only
     * between beginFrame() and endFrame(); empty string when unsupported.
     * @throws DrawingException Outside a frame.
     */
    public function readPixels(): string;

    /** Present. Every per-frame native resource is dropped here. */
    public function endFrame(): void;

    /** Terminal. */
    public function release(): void;
}

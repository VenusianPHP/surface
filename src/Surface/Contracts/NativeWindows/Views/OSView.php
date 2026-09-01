<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * One node conjured into a window's content.
 *
 * Coordinates are TOP-LEFT absolute pixels inside the window content —
 * Surface's promise on both engines. AppKit's bottom-left origin is the
 * engine package's problem to invert, never the sketch's.
 */
interface OSView
{
    public function name(): string;

    /** Move and size the view. Top-left origin, pixels. */
    public function place(int $x, int $y, int $width, int $height): static;

    /** @return array{x: int, y: int, width: int, height: int} The frame Surface last placed. */
    public function frame(): array;

    /** Size the frame to the content's natural size, keeping the origin. */
    public function hug(): static;

    /** Centre inside the window content, optionally offset by ($dx, $dy) pixels. */
    public function center(int $dx = 0, int $dy = 0): static;

    /** Centre horizontally only, optionally offset; y stays where place() put it. */
    public function centerX(int $dx = 0): static;

    /** Re-resolve the stored placement rules against the current content size. */
    public function relayout(): static;

    /** Fill the view's own frame with a colour. */
    public function setBackground(Color $color): static;

    /** Terminal: destroys the native node and frees the name. The handle is dead after. */
    public function remove(): void;
}

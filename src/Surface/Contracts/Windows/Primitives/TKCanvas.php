<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Drawing\WindowOutput;
use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * A rectangle of the layout whose pixels the application draws. It hands out
 * a framebuffer bound to it, and present() puts that framebuffer on screen,
 * stretched over the view, opaque. Draw into the framebuffer directly, or
 * hand it to a rendering engine. A GPU engine borrows a surface inside it instead (WindowOutput). A click
 * on it posts CanvasClicked (view.clicked) with the point; watchPointer() adds the presses, drags,
 * releases and wheels behind it.
 */
interface TKCanvas extends TKPrimitive, WindowOutput
{
    /**
     * The canvas in device pixels: size() times the display's scale, so 2x on a HiDPI display.
     * [0, 0] until the toolkit has laid the view out.
     *
     * @return array{int, int} width, height
     */
    public function pixelSize(): array;

    /**
     * The framebuffer bound to this canvas, in RGBA8. The same one is answered
     * while its kind and size still match; otherwise a new one is made and bound,
     * so calling this again after a resize gives one at the new size.
     *
     * @param  string  $kind  'dirty', 'full' or 'ring'
     * @param  int|null  $width  Pixels across; pixelSize()'s unless given. A smaller framebuffer is stretched over the view.
     * @param  int|null  $height  Pixels down; pixelSize()'s unless given.
     * @param  int  $frames  Frames of a ring.
     * @param  string|null  $driver  Where its bytes live: 'native' or 'extended'; config's unless given.
     *
     * @throws \Surface\Contracts\Windows\WindowException When no size is given and the view has none yet, or the kind is not one of the three.
     */
    public function framebuffer(string $kind = 'dirty', ?int $width = null, ?int $height = null, int $frames = 2, ?string $driver = null): Framebuffer;

    /** The framebuffer bound now; null before framebuffer() is first called. */
    public function boundFramebuffer(): ?Framebuffer;

    /**
     * Show the bound framebuffer. A dirty framebuffer with no damage, and a ring
     * that has presented no new frame, change nothing on screen; a dirty
     * framebuffer's epoch is begun anew once shown.
     *
     * @throws \Surface\Contracts\Windows\WindowException When no framebuffer is bound.
     */
    public function present(): static;

    /**
     * Watch the pointer over this canvas, or stop watching. While watching it posts CanvasPressed,
     * CanvasDragged, CanvasReleased (view.pressed, view.dragged, view.released) and CanvasWheeled
     * (view.wheeled), in points from its top-left as CanvasClicked's. CanvasClicked and the
     * right-click and hold rules post as they do otherwise, watching or not: a sketch that wants
     * only clicks never watches.
     *
     * Press rules. One press at a time per window: the first button down on a watching, enabled
     * canvas owns the press, and other buttons' presses and releases post nothing until it ends.
     * CanvasDragged posts on each move while that button is held, only when the point changed, and
     * goes on outside the canvas and the window through the toolkit's implicit capture. Every
     * CanvasPressed is followed by exactly one CanvasReleased: at the owning button's release, or,
     * when the toolkit ends the press without one (a context menu opens, GTK's grab breaks, a touch
     * is cancelled), at once, at the last point posted. Ending a press from code — watchPointer(false),
     * disabling the canvas or a group above it, removing it, closing the window — drops it silently:
     * no mail comes from code, here as everywhere. A touch the toolkit emulates a pointer with is
     * the left button, and clicks counts presses of the same button on the same canvas within the
     * toolkit's double-click time and distance, so a triple click is 3 on every toolkit.
     *
     * Wheel rules. A wheel over a watching, enabled canvas is the canvas's: posted and not passed
     * on, so an enclosing scroll view does not scroll. Not watching, it passes as it does otherwise.
     *
     * @throws \Surface\Contracts\Windows\WindowException Once removed.
     */
    public function watchPointer(bool $on = true): static;

    /** Whether the pointer is being watched; false until watchPointer() is called. */
    public function isWatchingPointer(): bool;

    /**
     * Let the canvas take keyboard focus by click and by Tab, as a native control does. With
     * $takesTab, Tab and Shift-Tab reach the focused canvas instead of moving focus (an editor
     * pane); without it they move focus on. A canvas that is not focusable never takes Tab.
     * Default off: a canvas takes no focus.
     * @throws \Surface\Contracts\Windows\WindowException Once removed.
     */
    public function setFocusable(bool $on = true, bool $takesTab = false): static;

    public function isFocusable(): bool;

    public function takesTab(): bool;
}

<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * A rectangle of the layout whose pixels the application draws. It hands out
 * a framebuffer bound to it, and present() puts that framebuffer on screen,
 * stretched over the view, opaque. Draw into the framebuffer directly, or
 * hand it to a rendering engine. Posts no mail of its own.
 */
interface TKCanvas extends TKPrimitive
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
     * @param  string  $kind  'full', 'dirty' or 'ring'
     * @param  int|null  $width  Pixels across; pixelSize()'s unless given. A smaller framebuffer is stretched over the view.
     * @param  int|null  $height  Pixels down; pixelSize()'s unless given.
     * @param  int  $frames  Frames of a ring.
     * @param  string|null  $driver  Where its bytes live: 'native' or 'extended'; config's unless given.
     *
     * @throws \Surface\Contracts\Windows\WindowException When no size is given and the view has none yet, or the kind is not one of the three.
     */
    public function framebuffer(string $kind = 'full', ?int $width = null, ?int $height = null, int $frames = 2, ?string $driver = null): Framebuffer;

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
}

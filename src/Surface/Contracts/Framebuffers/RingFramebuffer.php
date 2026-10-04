<?php

namespace Surface\Contracts\Framebuffers;

/**
 * A swap chain of whole frames. One frame is the front: the last finished
 * picture, for readers. One is the back: the picture being drawn. present()
 * makes the back the front and picks the next back.
 *
 * On the ring itself the drawing calls (getPixel, setPixel, setPixels,
 * setRegion, setSegment, clear, fill, blitFrom) address the back, and the
 * draining calls (dump, flush, flushRegion, toRgba8, blitTo, pointer) address
 * the front, so a reader never sees a half-drawn frame.
 *
 * A reader that spans loop turns holds the front; a held frame is never drawn
 * into. With two frames the drawer waits for the reader's release; with three
 * or more it never waits, and frames the reader was too slow for are dropped.
 * Frames beyond the third are history.
 */
interface RingFramebuffer extends Framebuffer
{
    public function frames(): int;

    /** Presents so far; the front frame's serial. 0 before the first present. */
    public function serial(): int;

    /** False while every frame but the front is held: nothing can be drawn until a release. */
    public function ready(): bool;

    /**
     * The back becomes the front. The next back is the frame no reader holds
     * that was presented longest ago.
     *
     * @throws FramebufferException When the ring is not ready.
     */
    public function present(): static;

    /** The newest finished frame. */
    public function front(): Framebuffer;

    /**
     * The frame being drawn.
     *
     * @throws FramebufferException When the ring is not ready.
     */
    public function back(): Framebuffer;

    /** A finished frame by age: 0 is the front, 1 the one before it. Null once it has been drawn over, or was never presented. */
    public function frame(int $age): ?Framebuffer;

    /**
     * The back's buffer age, as EGL_EXT_buffer_age defines it: 0 when its
     * contents are undefined (never presented), 1 when it equals the front,
     * n when it holds the frame presented n - 1 presents before the front.
     */
    public function age(): int;

    /**
     * Bring the back up to date: copy from the front every region that changed
     * since the frame the back holds. Afterwards age() is 1 and only this
     * frame's own changes need drawing.
     */
    public function repair(): static;

    /** Pin the front for a reader. It is never drawn into until every hold on it is released. */
    public function hold(): Framebuffer;

    /** @throws FramebufferException When $frame is not a held frame of this ring. */
    public function release(Framebuffer $frame): static;

    /**
     * What differs between the front and the frame with serial $since, snapped
     * to damageGranularity(): the regions a reader that last drained $since
     * must send. Null means the frame before the front. The whole surface when
     * the record no longer reaches back that far.
     *
     * @return list<Region>
     */
    public function damage(?int $since = null): array;
}

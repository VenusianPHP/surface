<?php

namespace Surface\Contracts\Windows;

use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * A video whose frames ext-ffmpeg decodes into an RGBA8 framebuffer. It is
 * drawn as any image is, with RenderingEngine::image(), so it lies in the
 * scene under and over whatever else is drawn, on every engine.
 *
 * The clock runs on its own while playing; advance() brings the framebuffer to
 * the frame due now. Whoever draws it calls advance() before a frame and draws
 * again when it answers true. The picture only: it plays no sound, and
 * setMuted() keeps the state.
 */
interface DecodedVideo extends StagedVideo
{
    /** The frame showing, at the picture's own pixel size; null with no file, or one with no picture. */
    public function framebuffer(): ?Framebuffer;

    /** @return array{int, int}|null the picture's width and height in pixels */
    public function size(): ?array;

    /** Frames a second, as the file says; 0.0 when it does not. */
    public function frameRate(): float;

    /**
     * Decode up to the frame due at position() and put it in the framebuffer.
     * Frames that are late are dropped; far behind (after a seek, or a stall)
     * it catches up over several calls, each bounded in time.
     *
     * @return bool whether the framebuffer holds a new frame
     */
    public function advance(): bool;
}

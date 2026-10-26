<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * An image on a push or toggle button, and the bordered look.
 */
interface HasButtonImage
{
    /**
     * Shown at width / scale × height / scale points.
     *
     * @param Framebuffer|null $image RGBA8 pixels with alpha; null removes it
     * @param ImagePosition $position
     * @param float $scale pixels per point: 2.0 shows a 32-pixel image at 16 points
     * @return $this
     * @throws \Surface\Contracts\Windows\WindowException When the scale is not positive.
     */
    public function setImage(?Framebuffer $image, ImagePosition $position = ImagePosition::LEADING, float $scale = 1.0): static;

    /**
     * @return Framebuffer|null
     */
    public function image(): ?Framebuffer;

    /**
     * @return ImagePosition
     */
    public function imagePosition(): ImagePosition;

    /**
     * @return float
     */
    public function imageScale(): float;

    /**
     * @param bool $on false: no bezel, the toolbar look
     * @return $this
     */
    public function setBordered(bool $on = true): static;

    /**
     * @return bool
     */
    public function isBordered(): bool;
}

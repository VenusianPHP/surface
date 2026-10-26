<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Windows\Primitives\ImagePosition;
use Surface\Contracts\Windows\WindowException;

/**
 * The image and bordered state of a push or toggle button (HasButtonImage).
 */
trait ShowsButtonImage
{
    protected ?Framebuffer $image = null;

    protected ImagePosition $image_position = ImagePosition::LEADING;

    protected float $image_scale = 1.0;

    protected bool $bordered = true;

    public function setImage(?Framebuffer $image, ImagePosition $position = ImagePosition::LEADING, float $scale = 1.0): static
    {
        $this->live();
        if ($scale <= 0) {
            throw new WindowException('Image scale must be positive.');
        }
        $this->image = $image;
        $this->image_position = $position;
        $this->image_scale = $scale;
        $this->applyImage($image, $position, $scale);

        return $this;
    }

    public function image(): ?Framebuffer
    {
        return $this->image;
    }

    public function imagePosition(): ImagePosition
    {
        return $this->image_position;
    }

    public function imageScale(): float
    {
        return $this->image_scale;
    }

    public function setBordered(bool $on = true): static
    {
        $this->live();
        if ($on !== $this->bordered) {
            $this->bordered = $on;
            $this->applyBordered($on);
        }

        return $this;
    }

    public function isBordered(): bool
    {
        return $this->bordered;
    }

    /**
     * Show the image at width / scale × height / scale points, or remove it.
     *
     * @param Framebuffer|null $image
     * @param ImagePosition $position
     * @param float $scale
     * @return void
     */
    abstract protected function applyImage(?Framebuffer $image, ImagePosition $position, float $scale): void;

    /**
     * @param bool $on
     * @return void
     */
    abstract protected function applyBordered(bool $on): void;
}

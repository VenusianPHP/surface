<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * An image file shown in a view. Posts no mail.
 */
interface TKImage extends TKPrimitive
{
    /**
     * @return string|null
     */
    public function file(): ?string;

    /**
     * @param string|null $file null clears the image
     * @return $this
     */
    public function setFile(?string $file): static;

    /**
     * @param ImageScaling $scaling
     * @return $this
     */
    public function setScaling(ImageScaling $scaling): static;
}

<?php

namespace Surface\Contracts\Drawing;

/** Minted by an executor, opaque to the sketch. Size in texels. */
final readonly class TextureHandle
{
    public function __construct(
        public int $id,
        public int $width,
        public int $height,
    ) {}
}

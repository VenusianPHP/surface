<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A picture loaded from a file path.
 *
 * A path is the one loading story both engines share. Bytes in memory are
 * the sketch's business: write a temp file, hand the path over.
 */
interface OSImage extends OSView
{
    /** The file path Surface last loaded, or null while the image is empty. */
    public function path(): ?string;

    public function setPath(string $path): static;
}

<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A group whose inner space can be larger than its frame. The engine draws
 * scrollbars and owns the scrolling; Surface owns the extent.
 */
interface OSScrollView extends OSGroup
{
    /**
     * Size the scrollable content extent. Children lay out against this
     * space; the frame stays the viewport.
     */
    public function setContentSize(int $width, int $height): static;

    /** @return array{int, int} The content extent [width, height]. */
    public function contentExtent(): array;
}

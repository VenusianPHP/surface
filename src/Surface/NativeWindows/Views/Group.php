<?php

namespace Surface\NativeWindows\Views;

/**
 * A plain container. Children lay out against its frame; the engine clips
 * what it can (GTK clips, AppKit layers do) and moving the group moves the
 * subtree natively.
 */
abstract class Group extends ViewGroup
{
    public function innerSize(): array
    {
        return [$this->width, $this->height];
    }
}

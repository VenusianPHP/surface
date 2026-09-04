<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A container node other views live inside.
 *
 * Children are conjured INTO a group (`in:` at conjure time, or the
 * group's own conjure sugar) — never reparented after the fact. A child's
 * coordinates and centering rules resolve against the group's inner space,
 * not the window content. Removal is terminal for the whole subtree.
 */
interface OSGroup extends OSView
{
    /**
     * The space children's rules resolve against, in pixels. A plain group
     * answers its own frame size; a scroll region answers its content extent.
     * @return array{int, int} [width, height]
     */
    public function innerSize(): array;

    /**
     * Record a conjured child. Called by Windowable during conjuring —
     * not a reparenting door.
     */
    public function registerChild(OSView $child): void;

    /** @return list<OSView> The children still alive, in conjure order. */
    public function children(): array;
}

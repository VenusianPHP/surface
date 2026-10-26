<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Windows\Menus\ContextMenu;

/**
 * A button with the platform's dropdown indicator that opens its menu under it. A chosen item
 * posts MenuActivated as a context menu's does.
 */
interface TKMenuButton extends TKPrimitive
{
    /**
     * @return string
     */
    public function label(): string;

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): static;

    /**
     * The image leads the label, shown at width / scale × height / scale points.
     *
     * @param Framebuffer|null $image RGBA8 pixels with alpha; null removes it
     * @param float $scale pixels per point
     * @return $this
     * @throws \Surface\Contracts\Windows\WindowException When the scale is not positive.
     */
    public function setImage(?Framebuffer $image, float $scale = 1.0): static;

    /**
     * @param bool $on false: no bezel, the toolbar look
     * @return $this
     */
    public function setBordered(bool $on = true): static;

    /**
     * @return bool
     */
    public function isBordered(): bool;

    /**
     * @return ContextMenu
     */
    public function menu(): ContextMenu;

    /**
     * @param array|ContextMenu $menu nodes as a context menu takes them
     * @return $this
     * @throws \Surface\Contracts\Windows\WindowException When a context menu refuses the nodes.
     */
    public function setMenu(array|ContextMenu $menu): static;
}

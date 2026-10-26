<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Windows\Menus\ContextMenu as ContextMenuContract;
use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKMenuButton as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\ContextMenu;

/**
 * The driver posts MenuActivated for a chosen item; nothing here posts.
 */
abstract class TKMenuButton extends TKPrimitive implements PrimitiveContract
{
    protected ContextMenuContract $menu;

    protected ?Framebuffer $image = null;

    protected float $image_scale = 1.0;

    protected bool $bordered = true;

    /**
     * @throws WindowException When the name is not valid.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected string $label,
        ContextMenuContract $menu,
    ) {
        $this->menu = $menu;
        parent::__construct($name, $window, $parent, $placement);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->live();
        $this->label = $label;
        $this->applyLabel($label);

        return $this;
    }

    public function setImage(?Framebuffer $image, float $scale = 1.0): static
    {
        $this->live();
        if ($scale <= 0) {
            throw new WindowException('Image scale must be positive.');
        }
        $this->image = $image;
        $this->image_scale = $scale;
        $this->applyImage($image, $scale);

        return $this;
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

    public function menu(): ContextMenuContract
    {
        return $this->menu;
    }

    public function setMenu(array|ContextMenuContract $menu): static
    {
        $this->live();
        $this->menu = is_array($menu) ? ContextMenu::parse($menu) : $menu;
        $this->applyMenu($this->menu);

        return $this;
    }

    /**
     * Engine callback: the user flipped a toggle of the button's menu; the menu keeps it.
     *
     * @param string $item
     * @param bool $on
     * @return void
     */
    public function nativeContextToggled(string $item, bool $on): void
    {
        if ($this->menu instanceof ContextMenu) {
            $this->menu = $this->menu->withToggle($item, $on);
        }
    }

    /**
     * Engine callback: the user chose a radio item of the button's menu; the menu keeps it.
     *
     * @param string $group
     * @param string $item
     * @return void
     */
    public function nativeContextRadio(string $group, string $item): void
    {
        if ($this->menu instanceof ContextMenu) {
            $this->menu = $this->menu->withRadio($group, $item);
        }
    }

    /**
     * @param string $label
     * @return void
     */
    abstract protected function applyLabel(string $label): void;

    /**
     * @param Framebuffer|null $image
     * @param float $scale
     * @return void
     */
    abstract protected function applyImage(?Framebuffer $image, float $scale): void;

    /**
     * Rebuild the native menu from these items.
     *
     * @param ContextMenuContract $menu
     * @return void
     */
    abstract protected function applyMenu(ContextMenuContract $menu): void;

    /**
     * @param bool $on
     * @return void
     */
    abstract protected function applyBordered(bool $on): void;
}

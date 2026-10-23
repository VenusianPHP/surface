<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\TKCanvas as PrimitiveContract;
use Surface\Windows\Concerns\HostsDrawing;

/**
 * The canvas, toolkit-neutral: it makes and binds the framebuffer and decides
 * what present() has to show. A toolkit's canvas supplies the native view,
 * its scale, and the one call that puts RGBA8 bytes on screen.
 */
abstract class TKCanvas extends TKPrimitive implements PrimitiveContract
{
    use HostsDrawing;

    /** Read by the window's pointer source; nothing is written to the native, as a context menu is not. */
    private bool $watching_pointer = false;

    private bool $focusable = false;

    private bool $takes_tab = false;

    public function watchPointer(bool $on = true): static
    {
        $this->live();
        $this->watching_pointer = $on;

        return $this;
    }

    public function isWatchingPointer(): bool
    {
        return $this->watching_pointer;
    }

    public function setFocusable(bool $on = true, bool $takesTab = false): static
    {
        $this->live();
        $takesTab = $on && $takesTab;
        if ($this->focusable !== $on || $this->takes_tab !== $takesTab) {
            $this->focusable = $on;
            $this->takes_tab = $takesTab;
            $this->applyFocusable($on, $takesTab);
        }

        return $this;
    }

    public function isFocusable(): bool
    {
        return $this->focusable;
    }

    public function takesTab(): bool
    {
        return $this->takes_tab;
    }

    /** Let the native view take focus (and Tab), or not. */
    abstract protected function applyFocusable(bool $on, bool $takesTab): void;

    /** No toolkit lends until its engine slice says what it lends. */
    public function surfaces(): array
    {
        return [];
    }

    /** Terminal, as every primitive's: a lent surface is reclaimed while the native view still exists. */
    public function remove(): void
    {
        $this->live();
        $this->reclaim();

        parent::remove();
    }

    protected function hostName(): string
    {
        return "Canvas '{$this->path()}'";
    }

    protected function hostKind(): string
    {
        return 'canvas';
    }
}

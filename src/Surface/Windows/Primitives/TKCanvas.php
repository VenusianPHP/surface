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

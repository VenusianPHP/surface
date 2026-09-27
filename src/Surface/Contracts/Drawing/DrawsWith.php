<?php

namespace Surface\Contracts\Drawing;

/**
 * A target that presents pixels something else rasterised, and will take a
 * different rasteriser without closing: a panel, a CPU stage. What it keeps is
 * its size, its format and its lifecycle; what changes is who draws.
 *
 * A GPU target does not implement this — it owns the surface its engine made,
 * so a Canvas renders offscreen and hands it one texture instead.
 */
interface DrawsWith
{
    /**
     * Rasterise through this canvas from here on. Same drawable size, same
     * host format; the hook is the caller's to set again.
     */
    public function drawWith(CPUDrawTarget $canvas): static;
}

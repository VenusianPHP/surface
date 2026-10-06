<?php

namespace Surface\Contracts\Drawing;

/**
 * What a window shows pixels through. Its own framebuffer goes up by the CPU
 * pipe (Pipeable). A GPU engine instead borrows a native surface inside it
 * and copies its frames in on the GPU: while a surface is lent,
 * boundFramebuffer() answers the borrower's framebuffer, framebuffer() throws,
 * and present() is the borrower's copy.
 */
interface WindowOutput extends Pipeable
{
    /**
     * The surface kinds this window can lend, on this toolkit and platform.
     *
     * @return list<SurfaceKind>
     */
    public function surfaces(): array;

    /**
     * Make a native surface of $kind inside the window, against the
     * borrower's lendingHandles(), and hand it out. One at a time.
     *
     * @throws \Surface\Contracts\Windows\WindowException When the window does not lend $kind, or a surface is already lent.
     */
    public function lend(SurfaceKind $kind, SurfaceBorrower $to): LentSurface;

    /** The surface out on loan; null when none is. */
    public function lent(): ?LentSurface;

    /** Remove the lent surface and release it; the window shows its own framebuffer again. Nothing when none is lent. */
    public function reclaim(): void;
}

<?php

namespace Surface\Contracts\Framebuffers;

/**
 * One page of rows over a taller virtual surface, for hosts that cannot hold a
 * whole frame. The caller draws the same picture once per page: writes outside
 * the current page are dropped, reads outside it answer 0. flush(),
 * flushRegion() and toRgba8() answer the current page's rows only.
 */
interface PagedFramebuffer extends Framebuffer
{
    public function pageRows(): int;

    public function pages(): int;

    /** Select a page; its rows start cleared. */
    public function setPage(int $page): static;

    public function page(): int;

    /** Where page $page sits on the virtual surface; the last page may be short. */
    public function pageRegion(int $page): Region;

    /**
     * The pages a region of the virtual surface touches: the ones to redraw and send for a partial refresh.
     *
     * @return list<int>
     */
    public function pagesTouching(Region $region): array;
}

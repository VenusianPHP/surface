<?php

namespace Surface\Contracts\Framebuffers;

/**
 * One page of RAM over a taller virtual surface. Writes outside the current
 * page are dropped; flush()/flushRegion() answer the current page only.
 */
interface PagedFramebuffer extends Framebuffer
{
    public function pageRows(): int;

    public function pages(): int;

    /** Select a page; the window is zeroed. */
    public function setPage(int $page): void;

    public function page(): int;

    /** Where page $page sits on the virtual surface; the last page may be short. */
    public function pageRegion(int $page): Region;
}

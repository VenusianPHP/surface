<?php

namespace Surface\Contracts\Drawing;

/**
 * The paged engine's target: one page of RAM, the hook run once per page, and
 * each page pushed out the moment it is finished.
 */
interface PagedDrawTarget extends CPUDrawTarget
{
    /** fn(Region $page, string|array $bytes): void — fired per page inside renderFrame(). Replace, not stack. */
    public function onPage(callable $sink, bool $as_array = false): static;
}

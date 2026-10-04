<?php

namespace Surface\Contracts\EmbeddedDisplays;

use Surface\Contracts\Drawing\Output;
use Surface\Contracts\Framebuffers\Framebuffer;

/**
 * An IC display panel as a drawing output, beside a toolkit canvas. Its
 * framebuffer is drawn into like any other; present() sends the panel what
 * changed, in the panel's own format, then refreshes a panel that needs it.
 * GPIO-free: the chip itself is reached through the concrete class.
 */
interface EmbeddedDisplay extends Output
{
    public function name(): string;

    /**
     * A framebuffer at the panel's size in the panel's format, bound to this
     * display. The same one is answered while its kind, size and format still
     * match the panel. A null kind lets the panel pick: epaper for a panel that
     * refreshes on command, dirty for one that takes region writes, full
     * otherwise.
     *
     * @param  string|null  $kind  full, dirty, epaper, paged or ring
     * @param  int|null  $page_rows  Rows per page of a paged framebuffer.
     * @param  int  $frames  Frames of a ring.
     * @param  string|null  $driver  native or extended; config's unless given.
     *
     * @throws EmbeddedDisplayException When closed, the kind is unknown, or a paged framebuffer has no or unaligned page rows.
     */
    public function framebuffer(?string $kind = null, ?int $page_rows = null, int $frames = 2, ?string $driver = null): Framebuffer;

    /**
     * Show a framebuffer made elsewhere: any format, the panel's size. Its
     * bytes are converted to the panel's format when sent. The next present is
     * whole.
     *
     * @throws EmbeddedDisplayException When closed or the size differs.
     */
    public function bind(Framebuffer $framebuffer): static;

    /**
     * The whole frame the first time, after show() and after a framebuffer is
     * bound; afterwards the framebuffer's damage where the panel takes region
     * writes. A paged framebuffer sends its current page and refreshes after
     * the last. Nothing while hidden or faulted.
     *
     * @throws EmbeddedDisplayException When closed or nothing is bound.
     */
    public function present(): static;

    /** Panel output on; the next present is whole. Throws when the panel cannot switch. */
    public function show(): static;

    /** Panel output off; presents send nothing while hidden. Throws when the panel cannot switch. */
    public function hide(): static;

    public function isVisible(): bool;

    /** Whether show() and hide() are available on this panel. */
    public function switchable(): bool;

    /** Terminal and idempotent: output off where the panel can, then the display leaves its manager. The chip and its bus stay the sketch's. */
    public function close(): void;

    public function isOpen(): bool;

    /** A panel call threw. Nothing more is sent; detach and attach again to recover. */
    public function faulted(): bool;

    public function fault(): ?\Throwable;
}

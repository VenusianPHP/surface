<?php

namespace Surface\Contracts\Bridge;

use Surface\Contracts\NativeWindows\OSWindow;

/**
 * A live link between PHPland and one OS windowing engine.
 *
 * Engine initialisation happens once per process and cannot be undone — neither
 * AppKit's finishLaunching() nor GTK's gtk_init has a counterpart. Connection is
 * the part that cycles: on macOS it raises and drops the Dock icon, on Linux it
 * is a no-op because nothing is visible until a window exists.
 */
interface BridgedOSSession
{
    /**
     * Bring the session up, initialising the engine first if it has never run.
     * @return static
     */
    public function connect(): static;

    /**
     * Take the session down, draining anything the engine still has queued.
     * @return void
     */
    public function disconnect(): void;

    /**
     * Report whether the session is currently connected.
     * @return bool
     */
    public function connected(): bool;

    /**
     * Advance the engine's event loop, spending up to $budget_ms waiting on it.
     *
     * A pump may block for the whole budget: GTK's iteration genuinely does when
     * its context is empty, so AppKit conforms to the slower promise rather than
     * the abstraction pretending both are instant.
     *
     * @param int $budget_ms Milliseconds the engine may spend. Zero drains without waiting.
     * @return int Units of work the engine dispatched. Always zero while disconnected.
     */
    public function pump(int $budget_ms = 0): int;

    /**
     * @param string $name
     * @param int $width
     * @param int $height
     * @return OSWindow
     */
    public function provisionNewWindow(string $name, int $width, int $height): OSWindow;
}

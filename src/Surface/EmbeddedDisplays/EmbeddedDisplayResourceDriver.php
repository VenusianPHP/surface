<?php

namespace Surface\EmbeddedDisplays;

use Voyager\Contracts\IOPools\IOResourceDriver;

/**
 * The dock's displays resource. One tick: one frame on every attached display.
 * A display with nothing to draw costs nothing. A frame that does draw spends
 * the panel's own bus time inside the tick — 128 bytes for one SSD1306 page
 * band, 115200 for a whole 240x240 RGB565 frame — which is why a panel that
 * addresses a window defaults to the dirty engine. A closed display has
 * already left the manager; a faulted one is skipped by its own guard.
 */
final class EmbeddedDisplayResourceDriver implements IOResourceDriver
{
    public function __construct(private readonly EmbeddedDisplayManager $manager) {}

    public function tick(): void
    {
        foreach ($this->manager->displays() as $display) {
            $display->renderFrame();
        }
    }
}

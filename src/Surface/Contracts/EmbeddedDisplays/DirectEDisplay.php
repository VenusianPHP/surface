<?php

namespace Surface\Contracts\EmbeddedDisplays;

use Surface\Contracts\Drawing\Pipeable;

/**
 * An embedded display fed straight from framebuffer memory: each region goes
 * as a panel window, its rows read by the bus out of the framebuffer's C
 * memory. No pixel byte passes through PHP. Needs a PipeablePanel whose bus
 * writes from memory, and an ext-fb framebuffer in the panel's format;
 * anything else is refused, never sent another way.
 */
interface DirectEDisplay extends EmbeddedDisplay, Pipeable {}

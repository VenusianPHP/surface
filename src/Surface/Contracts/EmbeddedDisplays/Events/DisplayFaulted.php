<?php

namespace Surface\Contracts\EmbeddedDisplays\Events;

use Voyager\Contracts\IOPools\Occurrence;

/** A panel write threw and the display stopped sending. Mailed once. Named display.faulted.<display>. */
final class DisplayFaulted implements Occurrence
{
    public readonly string $name;

    public function __construct(public readonly string $display, public readonly string $error)
    {
        $this->name = "display.faulted.{$display}";
    }
}

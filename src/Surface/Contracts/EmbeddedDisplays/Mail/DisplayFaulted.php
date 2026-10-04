<?php

namespace Surface\Contracts\EmbeddedDisplays\Mail;

use Voyager\Contracts\Signals\NamedSignal;

/** A panel call threw and the display stopped sending. Posted once, named display.faulted.<display>. */
readonly class DisplayFaulted implements NamedSignal
{
    public function __construct(public string $display, public string $error) {}

    public function name(): string
    {
        return "display.faulted.{$this->display}";
    }
}

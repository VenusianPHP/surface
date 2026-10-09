<?php

namespace Surface\Contracts\Windows\Mail;

use Voyager\Contracts\Signals\NamedSignal;

/** A display was added or removed, or one changed mode, bounds or scale: ask the stager for displays() again. */
readonly class DisplaysChanged implements NamedSignal
{
    public function name(): string
    {
        return 'displays.changed';
    }
}

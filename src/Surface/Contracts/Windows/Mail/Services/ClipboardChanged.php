<?php

namespace Surface\Contracts\Windows\Mail\Services;

use Voyager\Contracts\Signals\NamedSignal;

/** The clipboard's content changed while the app runs, by another app or by Clipboard::setText(). Latest-wins. */
readonly class ClipboardChanged implements NamedSignal
{
    public function name(): string
    {
        return 'clipboard.changed';
    }
}

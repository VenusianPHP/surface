<?php

namespace Surface\Contracts\Windows\Mail\Services;

use Voyager\Contracts\Signals\NamedSignal;

/**
 * The answer of Dialogs::alert(): the index of the button, the cancel index for Esc and the
 * alert's own close, -1 when the parent closed first.
 */
readonly class AlertAnswered implements NamedSignal
{
    public function __construct(
        public string $id,
        public int $button,
    ) {}

    public function name(): string
    {
        return "dialog.alert.{$this->id}";
    }
}

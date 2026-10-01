<?php

namespace Surface\Contracts\Windows\Mail;

use Voyager\Contracts\Signals\NamedSignal;

/**
 * The Quit item was chosen. Nothing has quit: the handler closes windows and stops the loop.
 */
readonly class QuitRequested implements NamedSignal
{
    /**
     * @param string|null $window the window whose bar held the item; null for the app-level bar
     */
    public function __construct(
        public readonly ?string $window = null
    ) {}

    public function name(): string
    {
        return 'quit.requested';
    }
}
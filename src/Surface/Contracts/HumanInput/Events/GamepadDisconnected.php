<?php

namespace Surface\Contracts\HumanInput\Events;

use Voyager\Contracts\Signals\NamedSignal;

/** A game pad or game controller went away, faulted circuits included. Named input.gamepad.disconnected.<id>. */
readonly class GamepadDisconnected implements NamedSignal
{
    public function __construct(public string $id) {}

    public function name(): string
    {
        return "input.gamepad.disconnected.{$this->id}";
    }
}

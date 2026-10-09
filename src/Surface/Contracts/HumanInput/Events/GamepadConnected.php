<?php

namespace Surface\Contracts\HumanInput\Events;

use Voyager\Contracts\Signals\NamedSignal;

/** A game pad or game controller appeared. Named input.gamepad.connected.<id>. */
readonly class GamepadConnected implements NamedSignal
{
    public function __construct(public string $id, public string $device_name) {}

    public function name(): string
    {
        return "input.gamepad.connected.{$this->id}";
    }
}

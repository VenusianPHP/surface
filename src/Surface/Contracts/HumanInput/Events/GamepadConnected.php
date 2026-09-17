<?php

namespace Surface\Contracts\HumanInput\Events;

use Voyager\Contracts\IOPools\Occurrence;

/** A gamepad or game controller appeared. Named input.gamepad.connected.<id>. */
final class GamepadConnected implements Occurrence
{
    public readonly string $name;

    public function __construct(public readonly string $id, public readonly string $device_name)
    {
        $this->name = "input.gamepad.connected.{$id}";
    }
}

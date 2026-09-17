<?php

namespace Surface\Contracts\HumanInput\Events;

use Voyager\Contracts\IOPools\Occurrence;

/** A gamepad or game controller went away. Named input.gamepad.disconnected.<id>. */
final class GamepadDisconnected implements Occurrence
{
    public readonly string $name;

    public function __construct(public readonly string $id)
    {
        $this->name = "input.gamepad.disconnected.{$id}";
    }
}

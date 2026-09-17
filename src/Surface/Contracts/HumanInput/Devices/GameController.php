<?php

namespace Surface\Contracts\HumanInput\Devices;

use Surface\Contracts\HumanInput\GamepadAxis;

/** What a sketch reads to ask about a game controller's sticks and triggers, in addition to GamePad's buttons. */
interface GameController extends GamePad
{
    /** @return list<GamepadAxis> */
    public function axes(): array;

    public function axis(GamepadAxis $axis): float;

    /** @return array{x: float, y: float} */
    public function leftStick(): array;

    /** @return array{x: float, y: float} */
    public function rightStick(): array;

    public function leftTrigger(): float;

    public function rightTrigger(): float;
}

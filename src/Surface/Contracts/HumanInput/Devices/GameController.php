<?php

namespace Surface\Contracts\HumanInput\Devices;

use Surface\Contracts\HumanInput\GamepadAxis;

/** A game pad with sticks: its axes this frame, in addition to GamePad's buttons. */
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

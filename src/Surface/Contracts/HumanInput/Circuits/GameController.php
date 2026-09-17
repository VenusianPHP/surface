<?php

namespace Surface\Contracts\HumanInput\Circuits;

use Surface\Contracts\HumanInput\GamepadAxis;

interface GameController extends ButtonPad
{
    /**
     * The axes this pad has. LEFT_X or RIGHT_X present makes it a game controller; absent, a game pad.
     *
     * @return list<GamepadAxis>
     */
    public function supportedAxes(): array;

    public function axis(GamepadAxis $axis): float;
}

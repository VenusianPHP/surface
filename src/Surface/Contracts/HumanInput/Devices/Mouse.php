<?php

namespace Surface\Contracts\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\MouseButton;

/** What a sketch reads to ask about the mouse as of the last poll. Wheel dy > 0 scrolls up, away from the user. */
interface Mouse
{
    public function x(): float;

    public function y(): float;

    public function window(): ?string;

    /** @return array{dx: float, dy: float} */
    public function motion(): array;

    /** @return array{dx: float, dy: float} */
    public function wheel(): array;

    public function button(MouseButton $button): ButtonState;

    public function isDown(MouseButton $button): bool;

    public function isPressed(MouseButton $button): bool;

    public function wasReleased(MouseButton $button): bool;
}

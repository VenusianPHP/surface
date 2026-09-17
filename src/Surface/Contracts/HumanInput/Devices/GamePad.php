<?php

namespace Surface\Contracts\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\GamepadButton;

/** What a sketch reads to ask about a gamepad as of the last poll. */
interface GamePad
{
    public function id(): string;

    public function name(): string;

    public function supports(GamepadButton $button): bool;

    public function button(GamepadButton $button): ButtonState;

    public function isDown(GamepadButton $button): bool;

    public function isPressed(GamepadButton $button): bool;

    public function wasReleased(GamepadButton $button): bool;

    public function isHolding(GamepadButton $button, int $hold_ms): bool;

    /** @return list<GamepadButton> */
    public function downButtons(): array;

    /** @return list<GamepadButton> */
    public function pressedButtons(): array;
}

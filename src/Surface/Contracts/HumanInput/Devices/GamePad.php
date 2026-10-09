<?php

namespace Surface\Contracts\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\Contracts\HumanInput\GamepadButton;

/** What a sketch reads to ask about a game pad this frame. */
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

    /** The same for one physical pad across reconnects (which bring a new id()), or null where its source has no identity. */
    public function hardwareId(): ?string;

    /** Light the pad's player LEDs for a 0-based player index; null clears them. A pad without them ignores it. */
    public function setPlayerIndex(?int $index): void;
}

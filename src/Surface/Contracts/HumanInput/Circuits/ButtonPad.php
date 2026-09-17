<?php

namespace Surface\Contracts\HumanInput\Circuits;

use Surface\Contracts\HumanInput\GamepadButton;

/** What an integrated circuit (or any polled pad) implements so Surface can wrap it. An implementer may widen each parameter with its own chip enum. */
interface ButtonPad
{
    public function poll(): static;

    public function connected(): bool;

    public function supports(GamepadButton $button): bool;

    public function isDown(GamepadButton $button): bool;

    public function isPressed(GamepadButton $button): bool;

    public function wasReleased(GamepadButton $button): bool;

    public function isHolding(GamepadButton $button, int $hold_ms): bool;
}

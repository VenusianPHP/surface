<?php

namespace Surface\Contracts\HumanInput\Devices;

use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;

/** What a sketch reads to ask about the keyboard this frame. */
interface Keyboard
{
    public function isDown(Key $key): bool;

    public function isPressed(Key $key): bool;

    public function wasReleased(Key $key): bool;

    public function isHolding(Key $key, int $hold_ms): bool;

    /** @return list<Key> */
    public function downKeys(): array;

    /** @return list<Key> */
    public function pressedKeys(): array;

    /** @return list<Key> */
    public function releasedKeys(): array;

    /** Text typed since the last frame, in the order typed. */
    public function text(): string;

    public function modifiers(): Modifiers;
}

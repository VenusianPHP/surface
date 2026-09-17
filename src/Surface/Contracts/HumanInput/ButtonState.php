<?php

namespace Surface\Contracts\HumanInput;

/** One button as of the last poll. isPressed / wasReleased are edges: true for the poll that saw the change. */
interface ButtonState
{
    public function isDown(): bool;

    public function isPressed(): bool;

    public function wasReleased(): bool;

    public function isHolding(int $hold_ms): bool;

    public function heldMs(): int;
}

<?php

namespace Surface\Contracts\HumanInput;

/** One button as of this frame. isPressed / wasReleased are edges: true in the frame that first reads the change. */
interface ButtonState
{
    public function isDown(): bool;

    public function isPressed(): bool;

    public function wasReleased(): bool;

    public function isHolding(int $hold_ms): bool;

    public function heldMs(): int;
}

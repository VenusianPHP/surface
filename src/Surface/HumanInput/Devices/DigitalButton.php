<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;

/**
 * One button. A source settles it at the top of a poll, then updates it once
 * per observation; edges OR within the poll so a tap shorter than a tick is
 * still seen. Hold time runs from the press, on the monotonic clock.
 */
final class DigitalButton implements ButtonState
{
    private bool $down = false;

    private bool $pressed = false;

    private bool $released = false;

    private ?int $down_since_ns = null;

    public function __construct(public readonly string $name) {}

    public function update(bool $down, ?int $at_ns = null): void
    {
        if ($down && ! $this->down) {
            $this->pressed = true;
            $this->down_since_ns = $at_ns ?? hrtime(true);
        }

        if (! $down && $this->down) {
            $this->released = true;
            $this->down_since_ns = null;
        }

        $this->down = $down;
    }

    public function settle(): void
    {
        $this->pressed = false;
        $this->released = false;
    }

    public function reset(): void
    {
        $this->down = $this->pressed = $this->released = false;
        $this->down_since_ns = null;
    }

    public function isDown(): bool
    {
        return $this->down;
    }

    public function isPressed(): bool
    {
        return $this->pressed;
    }

    public function wasReleased(): bool
    {
        return $this->released;
    }

    public function isHolding(int $hold_ms): bool
    {
        return $this->down && $this->heldMs() >= $hold_ms;
    }

    public function heldMs(): int
    {
        return is_null($this->down_since_ns) ? 0 : intdiv(hrtime(true) - $this->down_since_ns, 1_000_000);
    }
}

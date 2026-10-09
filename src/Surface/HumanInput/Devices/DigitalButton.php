<?php

namespace Surface\HumanInput\Devices;

use Surface\Contracts\HumanInput\ButtonState;
use Surface\HumanInput\InputFrame;

/**
 * One button. Updated once per observation; edges add up until settle(), so a tap
 * shorter than a frame reads pressed and released together. Hold time runs from the
 * press, on the monotonic clock. Every state read tells the frame.
 */
final class DigitalButton implements ButtonState
{
    private bool $down = false;

    private bool $pressed = false;

    private bool $released = false;

    private ?int $down_since_ns = null;

    public function __construct(private readonly InputFrame $frame, public readonly string $name) {}

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

    public function isDown(): bool
    {
        $this->frame->read();

        return $this->down;
    }

    public function isPressed(): bool
    {
        $this->frame->read();

        return $this->pressed;
    }

    public function wasReleased(): bool
    {
        $this->frame->read();

        return $this->released;
    }

    public function isHolding(int $hold_ms): bool
    {
        return $this->isDown() && $this->heldMs() >= $hold_ms;
    }

    public function heldMs(): int
    {
        $this->frame->read();

        return is_null($this->down_since_ns) ? 0 : intdiv(hrtime(true) - $this->down_since_ns, 1_000_000);
    }
}

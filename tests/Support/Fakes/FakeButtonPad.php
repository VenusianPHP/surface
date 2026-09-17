<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\HumanInput\Circuits\ButtonPad;
use Surface\Contracts\HumanInput\GamepadButton;

/** A circuit whose held buttons and edges the test sets directly. Edges are the pressed / released lists the test scripts; a set failure is thrown from poll() / connected(). */
final class FakeButtonPad implements ButtonPad
{
    public int $polls = 0;

    public bool $connected = true;

    /** @var list<GamepadButton> */
    public array $down = [];

    /** @var list<GamepadButton> */
    public array $pressed = [];

    /** @var list<GamepadButton> */
    public array $released = [];

    public ?\Throwable $poll_failure = null;

    public ?\Throwable $connected_failure = null;

    /** @param list<GamepadButton> $supported */
    public function __construct(public array $supported) {}

    public function poll(): static
    {
        $this->polls++;

        if (! is_null($this->poll_failure)) {
            throw $this->poll_failure;
        }

        return $this;
    }

    public function connected(): bool
    {
        if (! is_null($this->connected_failure)) {
            throw $this->connected_failure;
        }

        return $this->connected;
    }

    public function supports(GamepadButton $button): bool
    {
        return in_array($button, $this->supported, true);
    }

    public function isDown(GamepadButton $button): bool
    {
        return in_array($button, $this->down, true);
    }

    public function isPressed(GamepadButton $button): bool
    {
        return in_array($button, $this->pressed, true);
    }

    public function wasReleased(GamepadButton $button): bool
    {
        return in_array($button, $this->released, true);
    }

    public function isHolding(GamepadButton $button, int $hold_ms): bool
    {
        return false;
    }
}

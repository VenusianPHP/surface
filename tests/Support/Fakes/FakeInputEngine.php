<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\HumanInput\InputEngine;
use Surface\Contracts\HumanInput\InputEngineDriver;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\Devices\Keyboard;
use Surface\HumanInput\Devices\Mouse;

/** An InputEngineDriver that counts lifecycle calls and serves whatever devices the test hands it. */
final class FakeInputEngine implements InputEngineDriver
{
    public int $polls = 0;

    public int $connects = 0;

    public int $disconnects = 0;

    public bool $connected = false;

    public ?Keyboard $keyboard = null;

    public ?Mouse $mouse = null;

    /** @var array<string, GamePad> */
    public array $pads = [];

    /** @var array<string, GameController> */
    public array $controllers = [];

    /** Thrown from disconnect() after it counts. */
    public ?\Throwable $disconnect_failure = null;

    public function __construct(public InputEngine $engine = InputEngine::SDL3) {}

    public function engine(): InputEngine
    {
        return $this->engine;
    }

    public function connect(): static
    {
        $this->connects++;
        $this->connected = true;

        return $this;
    }

    public function disconnect(): void
    {
        $this->disconnects++;
        $this->connected = false;

        if (! is_null($this->disconnect_failure)) {
            throw $this->disconnect_failure;
        }
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    public function poll(): void
    {
        $this->polls++;
    }

    public function keyboard(): ?Keyboard
    {
        return $this->keyboard;
    }

    public function mouse(): ?Mouse
    {
        return $this->mouse;
    }

    /** @return array<string, GamePad> */
    public function gamePads(): array
    {
        return $this->pads;
    }

    /** @return array<string, GameController> */
    public function gameControllers(): array
    {
        return $this->controllers;
    }
}

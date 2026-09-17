<?php

namespace Surface\Contracts\HumanInput;

/** A source of OS input behind an input.<engine> container alias. poll() never waits. Devices it cannot serve answer null or []. */
interface InputEngineDriver
{
    public function engine(): InputEngine;

    public function connect(): static;

    public function disconnect(): void;

    public function connected(): bool;

    public function poll(): void;

    public function keyboard(): ?Devices\Keyboard;

    public function mouse(): ?Devices\Mouse;

    /** @return array<string, Devices\GamePad> keyed by id; GameControllers excluded */
    public function gamePads(): array;

    /** @return array<string, Devices\GameController> keyed by id */
    public function gameControllers(): array;
}

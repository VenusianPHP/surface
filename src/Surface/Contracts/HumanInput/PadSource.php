<?php

namespace Surface\Contracts\HumanInput;

/**
 * The process's game pads, for every toolkit's windows and for scripts with none. One runs
 * per process: registered with HumanInputManager::extendPads() and named by
 * human-input.pads.<os>. Engines list no pads, so each pad is listed once.
 */
interface PadSource extends InputSource
{
    /** @return array<string, Devices\GamePad> keyed by id; game controllers excluded */
    public function gamePads(): array;

    /** @return array<string, Devices\GameController> keyed by id */
    public function gameControllers(): array;
}

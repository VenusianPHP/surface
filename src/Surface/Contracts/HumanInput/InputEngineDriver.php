<?php

namespace Surface\Contracts\HumanInput;

/**
 * One toolkit's keyboard and mouse, read through a tap on that toolkit's session. The
 * engine's package registers a creator with HumanInputManager::extend() under the
 * toolkit's bridge name; HumanInput starts the engine when that toolkit's session
 * connects. Devices are built from Surface\HumanInput\Devices, so reading them marks the
 * frame and the merged mouse can tell which engine reported last. Pads are listed only
 * when the creator was told no OS pad source is named.
 */
interface InputEngineDriver extends InputSource
{
    public function keyboard(): Devices\Keyboard;

    public function mouse(): Devices\Mouse;
}

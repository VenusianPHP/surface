<?php

namespace Surface\HumanInput\MagicAliases;

use Surface\Contracts\HumanInput\Circuits\ButtonPad;
use Surface\Contracts\HumanInput\Devices\GameController;
use Surface\Contracts\HumanInput\Devices\GamePad;
use Surface\Contracts\HumanInput\Devices\Keyboard;
use Surface\Contracts\HumanInput\Devices\Mouse;
use Surface\HumanInput\HumanInputManager;
use Surface\HumanInput\ICInput;

/** The application's HumanInput, read from anywhere in a sketch. */
final class HumanInput
{
    public static function manager(): HumanInputManager
    {
        /** @var HumanInputManager */
        return app('human-input');
    }

    public static function keyboard(): Keyboard
    {
        return self::manager()->keyboard();
    }

    public static function mouse(): Mouse
    {
        return self::manager()->mouse();
    }

    /** @return array<string, GamePad> */
    public static function gamePads(): array
    {
        return self::manager()->gamePads();
    }

    /** @return array<string, GameController> */
    public static function gameControllers(): array
    {
        return self::manager()->gameControllers();
    }

    public static function attach(ButtonPad $ic, string $name): ICInput
    {
        return self::manager()->attach($ic, $name);
    }

    public static function detach(string $name): void
    {
        self::manager()->detach($name);
    }

    /** One poll, for a script that runs no loop. */
    public static function poll(): void
    {
        self::manager()->poll();
    }
}

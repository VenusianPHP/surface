<?php

namespace Surface\Contracts\HumanInput;

use Surface\Contracts\Core\SurfaceLevelException;

/** Input failures Surface can name. Engine and circuit packages subclass it so a sketch catches one type. */
class HumanInputException extends SurfaceLevelException
{
    public static function nameTaken(string $name): static
    {
        return new static("Input circuit '{$name}' is already attached.");
    }

    public static function noSuchCircuit(string $name): static
    {
        return new static("No input circuit named '{$name}'.");
    }

    public static function unsupportedButton(string $device, GamepadButton $button): static
    {
        return new static("'{$device}' has no {$button->value} button.");
    }

    public static function unsupportedAxis(string $device, GamepadAxis $axis): static
    {
        return new static("'{$device}' has no {$axis->value} axis.");
    }

    public static function notConnected(InputEngine $engine): static
    {
        return new static("The '{$engine->value}' input engine is not connected.");
    }
}

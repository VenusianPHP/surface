<?php

namespace Surface\Contracts\HumanInput;

use Surface\Contracts\Core\SurfaceException;

/** Input failures Surface can name. Engine and circuit packages subclass it so a sketch catches one type. */
class HumanInputException extends SurfaceException
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

    public static function noEngine(string $toolkit): static
    {
        return new static("No input engine is registered for the '{$toolkit}' toolkit: install the package that provides it.");
    }

    /** @param list<string> $registered */
    public static function noPadSource(string $name, string $os, array $registered): static
    {
        $known = $registered === [] ? 'none' : implode(', ', $registered);

        return new static("human-input.pads.{$os} names the pad source '{$name}', which no package registered (registered: {$known}): install the package that provides it, name a registered one, or set it to null.");
    }
}

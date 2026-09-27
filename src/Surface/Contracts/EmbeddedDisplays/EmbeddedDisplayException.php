<?php

namespace Surface\Contracts\EmbeddedDisplays;

use Surface\Contracts\Core\SurfaceLevelException;

/** Embedded display failures Surface can name. */
class EmbeddedDisplayException extends SurfaceLevelException
{
    public static function nameTaken(string $name): static
    {
        return new static("Embedded display '{$name}' is already attached.");
    }

    public static function noCatalog(): static
    {
        return new static('No circuit catalog is bound. Install scrapyard-io/framework (or gpio/integrated-circuits) to conjure a panel.');
    }

    public static function notADisplayPanel(string $profile, string $type): static
    {
        return new static("Circuit [{$profile}] conjured a {$type}, which is not a display panel Surface can draw for.");
    }

    public static function rendererMismatch(string $name): static
    {
        return new static("A renderer for embedded display '{$name}' must draw at the panel's own size and format.");
    }

    public static function noSuchDisplay(string $name): static
    {
        return new static("No embedded display named '{$name}'.");
    }

    public static function notBooted(string $name): static
    {
        return new static("The panel for embedded display '{$name}' has not booted. Boot it first (boot_now: true).");
    }

    public static function hostMismatch(string $name, int $width, int $height): static
    {
        return new static("The canvas host for embedded display '{$name}' must be {$width}x{$height} in the panel's own format.");
    }

    public static function pagedNeedsWindow(string $name): static
    {
        return new static("Embedded display '{$name}' cannot use the paged engine: its panel does not address a window.");
    }

    public static function notSwitchable(string $name): static
    {
        return new static("The panel for embedded display '{$name}' cannot be switched on or off.");
    }

    public static function closed(string $name): static
    {
        return new static("Embedded display '{$name}' is closed.");
    }
}

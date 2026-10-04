<?php

namespace Surface\Contracts\EmbeddedDisplays;

use Surface\Contracts\Core\SurfaceException;

/** Embedded display failures Surface can name. */
class EmbeddedDisplayException extends SurfaceException
{
    public static function nameTaken(string $name): static
    {
        return new static("Embedded display '{$name}' is already attached.");
    }

    public static function noCatalog(): static
    {
        return new static('No circuit catalog is bound. Install scrapyard-io/framework to conjure a panel.');
    }

    public static function notADisplayPanel(string $profile, string $type): static
    {
        return new static("Circuit [{$profile}] conjured a {$type}, which is not a display panel with a formatSpec().");
    }

    public static function noSuchDisplay(string $name): static
    {
        return new static("No embedded display named '{$name}'.");
    }

    public static function notBooted(string $name): static
    {
        return new static("The panel for embedded display '{$name}' has not booted. Boot it first (boot_now: true).");
    }

    public static function pagedNeedsWindow(string $name): static
    {
        return new static("Embedded display '{$name}' cannot show a paged framebuffer: its panel takes whole frames only.");
    }

    public static function pageRowsUnaligned(string $name, int $rows, int $unit): static
    {
        return new static("Embedded display '{$name}' needs page_rows in multiples of {$unit}, got {$rows}.");
    }

    public static function pageRowsMissing(string $name): static
    {
        return new static("Embedded display '{$name}' needs page_rows for a paged framebuffer.");
    }

    public static function notSwitchable(string $name): static
    {
        return new static("The panel for embedded display '{$name}' cannot be switched on or off.");
    }

    public static function closed(string $name): static
    {
        return new static("Embedded display '{$name}' is closed.");
    }

    public static function sizeMismatch(string $name, int $width, int $height, int $framebuffer_width, int $framebuffer_height): static
    {
        return new static("Embedded display '{$name}' is {$width}x{$height}; a {$framebuffer_width}x{$framebuffer_height} framebuffer cannot be bound to it.");
    }

    public static function nothingBound(string $name): static
    {
        return new static("Embedded display '{$name}' has no framebuffer: call framebuffer() or bind() first.");
    }

    public static function unknownKind(string $kind): static
    {
        return new static("A display framebuffer is 'full', 'dirty', 'epaper', 'paged' or 'ring', got '{$kind}'.");
    }
}

<?php

namespace Surface\Contracts\Stage;

use Surface\Contracts\Core\SurfaceLevelException;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Drawing\SurfaceKind;
use Throwable;

/** Stage failures Surface can name. Host packages subclass it so a sketch catches one type. */
class StageException extends SurfaceLevelException
{
    public static function unsupported(StageHost $host, GPUEngine $engine, SurfaceKind $kind): static
    {
        return new static("The '{$host->value}' stage host cannot give a '{$engine->value}' engine a {$kind->name} surface.");
    }

    /** The engine's own attach() failed on this host. The engine's exception rides as previous. */
    public static function attachFailed(StageHost $host, GPUEngine $engine, Throwable $previous): static
    {
        return new static("The '{$host->value}' stage host could not attach a '{$engine->value}' engine: {$previous->getMessage()}", 0, $previous);
    }

    public static function nameTaken(string $name): static
    {
        return new static("Stage '{$name}' already exists.");
    }

    public static function noSuchStage(string $name): static
    {
        return new static("No stage named '{$name}'.");
    }

    public static function notConnected(StageHost $host): static
    {
        return new static("The '{$host->value}' stage host is not connected.");
    }

    public static function closed(string $name): static
    {
        return new static("Stage '{$name}' is closed.");
    }

    public static function cpuUnsupported(StageHost $host): static
    {
        return new static("The '{$host->value}' stage host cannot present a CPU canvas.");
    }
}

<?php

namespace Surface\Contracts\Audio;

use Surface\Contracts\Core\SurfaceException;

class AudioException extends SurfaceException
{
    public static function extensionMissing(string $driver, string $extension): self
    {
        return new self("The '{$driver}' audio driver needs {$extension} loaded in this PHP (".PHP_BINARY.').');
    }

    public static function noDriver(): self
    {
        return new self('No audio driver can run in this PHP ('.PHP_BINARY.'): ext-miniaudio is not loaded.');
    }

    public static function refused(string $driver, string $what, string $reason): self
    {
        return new self("The '{$driver}' audio driver could not {$what}: {$reason}.");
    }
}

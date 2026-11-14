<?php

namespace Surface\Contracts\Video;

use Surface\Contracts\Core\SurfaceException;

class VideoException extends SurfaceException
{
    public static function threadsMissing(): self
    {
        return new self('A video decodes on worker threads: this PHP ('.PHP_BINARY.') needs a thread-safe (ZTS) build with ext-parallel loaded.');
    }

    public static function framebuffersMissing(): self
    {
        return new self('A video decodes into memory at an address: this PHP ('.PHP_BINARY.") needs ext-fb, the 'extended' framebuffers driver.");
    }

    public static function extensionMissing(string $driver, string $extension): self
    {
        return new self("The '{$driver}' video driver needs {$extension} loaded in this PHP (".PHP_BINARY.').');
    }

    public static function unreadable(string $source, string $reason): self
    {
        return new self("Video '{$source}' cannot be read: {$reason}.");
    }

    public static function noPicture(string $source): self
    {
        return new self("Video '{$source}' has no picture this driver decodes.");
    }
}

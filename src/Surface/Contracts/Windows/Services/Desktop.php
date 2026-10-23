<?php

namespace Surface\Contracts\Windows\Services;

use InvalidArgumentException;

interface Desktop
{
    /**
     * Open the file or folder in its default app. A relative path resolves against the working directory.
     *
     * @throws InvalidArgumentException When nothing is at the path.
     */
    public function open(string $path): void;
}

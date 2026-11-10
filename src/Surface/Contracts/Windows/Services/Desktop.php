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

    /**
     * Open a web address in the default browser. Only http and https addresses with a host.
     *
     * @throws InvalidArgumentException When the address is not an http or https one with a host.
     */
    public function browse(string $url): void;
}

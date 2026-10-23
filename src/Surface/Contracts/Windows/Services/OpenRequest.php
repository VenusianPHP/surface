<?php

namespace Surface\Contracts\Windows\Services;

use InvalidArgumentException;

final readonly class OpenRequest
{
    /**
     * @param string|null $title
     * @param string|null $directory where the panel starts; not a folder: the toolkit's own
     * @param bool $folders choose folders instead of files
     * @param bool $multiple files only
     * @throws InvalidArgumentException With both $folders and $multiple.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $directory = null,
        public bool $folders = false,
        public bool $multiple = false,
    ) {
        if ($folders && $multiple) {
            throw new InvalidArgumentException('An open request chooses one folder: folders and multiple cannot both be set.');
        }
    }
}

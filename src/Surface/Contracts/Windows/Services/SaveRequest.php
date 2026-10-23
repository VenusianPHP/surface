<?php

namespace Surface\Contracts\Windows\Services;

final readonly class SaveRequest
{
    /**
     * @param string|null $title
     * @param string|null $directory where the panel starts; not a folder: the toolkit's own
     * @param string|null $name the file name the panel suggests
     */
    public function __construct(
        public ?string $title = null,
        public ?string $directory = null,
        public ?string $name = null,
    ) {}
}

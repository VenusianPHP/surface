<?php

namespace Surface\Contracts\Windows\Services;

use InvalidArgumentException;

final readonly class SaveRequest
{
    use ChecksFileFilters;

    /**
     * @param string|null $title
     * @param string|null $directory where the panel starts; not a folder: the toolkit's own
     * @param string|null $name the file name the panel suggests
     * @param array<string, list<string>> $filters filter name => glob patterns ('Text' => ['*.txt', '*.md'])
     * @param bool $showHidden needs DialogCapability::ShowHidden
     * @throws InvalidArgumentException With a filter that is not a non-empty name and a list of non-empty patterns.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $directory = null,
        public ?string $name = null,
        public array $filters = [],
        public bool $showHidden = false,
    ) {
        self::checkFilters($filters);
    }
}

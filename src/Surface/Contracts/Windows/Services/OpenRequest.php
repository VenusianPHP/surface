<?php

namespace Surface\Contracts\Windows\Services;

use InvalidArgumentException;

final readonly class OpenRequest
{
    use ChecksFileFilters;

    /**
     * @param string|null $title
     * @param string|null $directory where the panel starts; not a folder: the toolkit's own
     * @param bool $folders choose folders instead of files
     * @param bool $multiple files only
     * @param array<string, list<string>> $filters filter name => glob patterns ('Text' => ['*.txt', '*.md']); files only
     * @param bool $showHidden needs DialogCapability::ShowHidden
     * @throws InvalidArgumentException With both $folders and $multiple, with both $folders and $filters, or with a filter that is not a non-empty name and a list of non-empty patterns.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $directory = null,
        public bool $folders = false,
        public bool $multiple = false,
        public array $filters = [],
        public bool $showHidden = false,
    ) {
        if ($folders && $multiple) {
            throw new InvalidArgumentException('An open request chooses one folder: folders and multiple cannot both be set.');
        }
        if ($folders && $filters !== []) {
            throw new InvalidArgumentException('An open request for folders takes no file filters: folders and filters cannot both be set.');
        }
        self::checkFilters($filters);
    }
}

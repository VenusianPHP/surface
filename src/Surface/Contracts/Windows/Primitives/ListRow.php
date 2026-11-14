<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * One list row: an image at the leading edge with an optional badge over its top-left corner,
 * a bold title line ending in status icons, detail text under it in a smaller face, and an
 * optional action button at the trailing bottom corner. A disabled row is dimmed and posts
 * nothing.
 */
readonly class ListRow
{
    /**
     * @param string|null $image an image file; null leaves the image space empty
     * @param string|null $badge short text over the image's top-left corner; null shows none
     * @param list<string> $icons image files after the title, in order
     * @param string|null $action the action button's label; null shows none
     */
    public function __construct(
        public ?string $image,
        public string $title,
        public string $detail = '',
        public ?string $badge = null,
        public array $icons = [],
        public ?string $action = null,
        public bool $enabled = true,
    ) {}
}

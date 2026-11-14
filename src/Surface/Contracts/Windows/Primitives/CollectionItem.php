<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * One collection cell: an image filling the cell, a caption under it, and an optional
 * action button in the top-right corner showing the $action image. Status icons float over the
 * image's top-right corner, left of the action button: shown only, never clicked, as a list row's
 * icons after its title.
 */
readonly class CollectionItem
{
    /**
     * @param string|null $image an image file; null shows an empty cell
     * @param string|null $action an image file for the corner action button; null shows none
     * @param list<string> $icons image files over the top-right corner, in order, left of the action button
     */
    public function __construct(
        public ?string $image,
        public string $caption = '',
        public ?string $action = null,
        public array $icons = [],
    ) {}
}

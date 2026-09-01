<?php

namespace Surface\NativeWindows\Views;

use Surface\Contracts\NativeWindows\Views\OSImage;
use Surface\NativeWindows\Enums\SizeRule;
use Surface\NativeWindows\Windowable;

/**
 * A picture loaded from a file path. Surface holds the path it believes
 * in; the engine loads and scales through applyPath(). Both engines fit
 * the picture inside the frame proportionally — the frame is layout,
 * the aspect ratio is the picture's.
 */
abstract class Image extends View implements OSImage
{
    public function __construct(
        string $name,
        Windowable $window,
        protected ?string $path = null,
    ) {
        parent::__construct($name, $window);
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;
        $this->applyPath($path);

        if ($this->sizing === SizeRule::NATURAL) {
            $this->relayout();
        }

        return $this;
    }

    /**
     * Load the file into the native node. Called for every setPath();
     * the mint hook owns any initial load when a path was conjured in.
     * @return void
     */
    abstract protected function applyPath(string $path): void;
}

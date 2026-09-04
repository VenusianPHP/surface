<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Voyager\Contracts\IOPools\Occurrence;

/**
 * A two-state control flipped (switch, toggle button, checkbox), named
 * `<window>.<view>.toggled`.
 */
class Toggled implements Occurrence
{
    public readonly string $name;

    public function __construct(
        string $view_name,
        string $window_name,
        public readonly bool $on,
    ) {
        $this->name = "{$window_name}.{$view_name}.toggled";
    }
}

<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Voyager\Contracts\IOPools\Occurrence;

/**
 * A dropdown's selection changed, named `<window>.<view>.selected`.
 */
class SelectionChanged implements Occurrence
{
    public readonly string $name;

    public function __construct(
        string $view_name,
        string $window_name,
        public readonly int $index,
        public readonly ?string $option,
    ) {
        $this->name = "{$window_name}.{$view_name}.selected";
    }
}

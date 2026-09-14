<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Voyager\Contracts\IOPools\Occurrence;

/**
 * A table's selected row changed, named `<window>.<view>.selected`.
 */
class RowSelected implements Occurrence
{
    public readonly string $name;

    /**
     * @param list<string> $cells
     */
    public function __construct(
        string $view_name,
        string $window_name,
        public readonly int $row,
        public readonly array $cells,
    ) {
        $this->name = "{$window_name}.{$view_name}.selected";
    }
}

<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Voyager\Contracts\IOPools\Occurrence;

/**
 * A date picker's day changed, named `<window>.<view>.changed`.
 */
class DateChanged implements Occurrence
{
    public readonly string $name;

    public function __construct(
        string $view_name,
        string $window_name,
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
        public readonly string $date,
    ) {
        $this->name = "{$window_name}.{$view_name}.changed";
    }
}

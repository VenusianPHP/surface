<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Voyager\Contracts\IOPools\Occurrence;

/**
 * A numeric control moved (a slider's thumb), named `<window>.<view>.changed`.
 */
class ValueChanged implements Occurrence
{
    public readonly string $name;

    public function __construct(
        string $view_name,
        string $window_name,
        public readonly float $value,
    ) {
        $this->name = "{$window_name}.{$view_name}.changed";
    }
}

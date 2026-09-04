<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Surface\Contracts\Core\Events\SurfaceEventType;
use Voyager\Contracts\IOPools\Occurrence;

class ButtonClicked implements Occurrence
{
    public readonly string $name;

    public function __construct(
        string $button_name,
        string $window_name,

    ) {
        $this->name = "{$window_name}.{$button_name}.clicked";
    }
}
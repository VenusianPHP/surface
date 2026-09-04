<?php

namespace Surface\Contracts\NativeWindows\Events\Window;

use Surface\Contracts\Core\Events\SurfaceEvent;
use Surface\Contracts\Core\Events\SurfaceEventType;

abstract class WindowedOccurrence extends SurfaceEvent
{
    public function __construct(
        string $window_name,
        SurfaceEventType $type,
    ) {
        parent::__construct(
            $type,
            "{$type->value}.{$window_name}",
            $window_name
        );

    }
}
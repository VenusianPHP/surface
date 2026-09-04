<?php

namespace Surface\Contracts\NativeWindows\Events\Window;

use Surface\Contracts\Core\Events\SurfaceEventType;

final class WindowClosed extends WindowedOccurrence
{
    public function __construct(
        string $window_name,
    ) {
        parent::__construct(
            $window_name,
            SurfaceEventType::WINDOW_CLOSED,
        );
    }
}
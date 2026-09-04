<?php

namespace Surface\Contracts\NativeWindows\Events\Window;

use Surface\Contracts\Core\Events\SurfaceEventType;

final class WindowResized extends WindowedOccurrence
{
    public function __construct(
        string $window_name,
        public readonly float $width,
        public readonly float $height,
    ) {
        parent::__construct(
            $window_name,
            SurfaceEventType::WINDOW_RESIZED,
        );
    }
}
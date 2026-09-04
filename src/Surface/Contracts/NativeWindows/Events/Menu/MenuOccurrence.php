<?php

namespace Surface\Contracts\NativeWindows\Events\Menu;

use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Events\Window\WindowedOccurrence;

class MenuOccurrence extends WindowedOccurrence
{
    public function __construct(
        string $window_name,
        public readonly string $event_name,
        public readonly string $id,
        public readonly string $label,
    ) {
        parent::__construct(
            $window_name,
            SurfaceEventType::MENU,
        );
    }
}

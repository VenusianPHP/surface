<?php

namespace Surface\Contracts\NativeWindows\Events\View;

use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Events\Window\WindowedOccurrence;

class ViewComponentOccurrence extends WindowedOccurrence
{
    public function __construct(
        string $window_name,
        SurfaceEventType $type,
        public readonly string $event_name,
        public readonly array $payload = []

    ) {
        parent::__construct(
            $window_name,
            $type,
        );
    }
}
<?php

namespace Surface\Contracts\NativeWindows\Events;

use Voyager\IOPools\Event;

/**
 * One thing an engine observed during a pump — the windowing vocabulary on
 * top of the framework's Event. The family is the type's backing value, so
 * generic consumers match broadly while Surface code keeps the enum.
 */
final class SurfaceEvent extends Event
{
    /**
     * @param array<string, mixed> $payload Family-specific detail.
     */
    public function __construct(
        public readonly SurfaceEventType $type,
        string $name,
        public readonly string $window,
        array $payload = [],
    ) {
        parent::__construct($type->value, $name, $payload);
    }
}

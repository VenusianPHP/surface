<?php

namespace Surface\Contracts\Core\Events;

use Voyager\Contracts\IOPools\Occurrence;

/**
 * One thing an engine observed during a pump — the windowing vocabulary on
 * top of the framework's Event. The family is the type's backing value, so
 * generic consumers match broadly while Surface code keeps the enum.
 */
abstract class SurfaceEvent implements Occurrence
{
    /**
     * @param array<string, mixed> $payload Family-specific detail.
     */
    public function __construct(
        public SurfaceEventType $type,
        public string $name,
        public string $window,
    ) {}
}

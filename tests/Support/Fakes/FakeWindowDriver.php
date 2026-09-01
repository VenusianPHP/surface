<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\OSWindow;
use Surface\NativeWindows\Drivers\NativeWindowDriver;

/**
 * A driver with no OS marker check, so the shared registry policy in
 * NativeWindowDriver can be asserted apart from either engine's type guard.
 */
final class FakeWindowDriver extends NativeWindowDriver
{
    public function add(OSWindow $window): static
    {
        $this->windows->put($window->name(), $window);

        return $this;
    }
}

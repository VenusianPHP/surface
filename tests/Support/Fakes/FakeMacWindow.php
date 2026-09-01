<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\MacOSWindow;

/** A fake carrying the macOS marker interface, so AppKitWindowDriver will accept it. */
final class FakeMacWindow extends FakeWindow implements MacOSWindow
{
    /** Times center() was asked for. */
    public int $centerings = 0;

    public function center(): static
    {
        $this->centerings++;

        return $this;
    }
}

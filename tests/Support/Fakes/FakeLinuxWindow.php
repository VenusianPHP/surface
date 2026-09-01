<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\Contracts\NativeWindows\LinuxOSWindow;

/** A fake carrying the Linux marker interface, so GTKWindowDriver will accept it. */
final class FakeLinuxWindow extends FakeWindow implements LinuxOSWindow
{
}

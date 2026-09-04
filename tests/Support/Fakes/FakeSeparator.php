<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\Separator;

/** A Separator whose engine hooks record instead of touching a toolkit. */
final class FakeSeparator extends Separator
{
    use RecordsViewApplies;
}

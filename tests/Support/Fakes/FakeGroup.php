<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\Group;

/** A Group whose engine hooks record instead of touching a toolkit. */
final class FakeGroup extends Group
{
    use RecordsViewApplies;
}

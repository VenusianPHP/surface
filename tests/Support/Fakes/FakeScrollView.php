<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\ScrollView;

/** A ScrollView whose engine hooks record instead of touching a toolkit. */
final class FakeScrollView extends ScrollView
{
    use RecordsViewApplies;

    /** @var list<array{int, int}> */
    public array $applied_content_sizes = [];

    protected function applyContentSize(int $width, int $height): void
    {
        $this->applied_content_sizes[] = [$width, $height];
    }
}

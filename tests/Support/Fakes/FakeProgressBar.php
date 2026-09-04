<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\ProgressBar;

/** A ProgressBar whose engine hooks record instead of touching a toolkit. */
final class FakeProgressBar extends ProgressBar
{
    use RecordsViewApplies;

    /** @var list<float> */
    public array $applied_progress = [];

    protected function applyProgress(float $progress): void
    {
        $this->applied_progress[] = $progress;
    }
}

<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Progress;

final class FakeProgress extends Progress
{
    use FakeControlCalls;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
    }

    protected function nativeSetFraction(float $fraction): void
    {
        $this->log->record('setFraction', $this->pointer(), $fraction);
    }
}

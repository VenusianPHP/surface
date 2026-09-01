<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Spinner;

final class FakeSpinner extends Spinner
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

    protected function nativeStart(): void
    {
        $this->log->record('start', $this->pointer());
    }

    protected function nativeStop(): void
    {
        $this->log->record('stop', $this->pointer());
    }
}

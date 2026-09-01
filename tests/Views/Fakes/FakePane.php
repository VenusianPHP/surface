<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Pane;
use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Size;

final class FakePane extends Pane
{
    use FakeParentCalls, FakeConjures;

    public Size $nativeSize;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
        $this->nativeSize = new Size(0, 0);
    }

    protected function nativeCurrentSize(): Size
    {
        $this->log->record('currentSize', $this->pointer());

        return $this->nativeSize;
    }
}

<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Views\Box;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\ParentView;

final class FakeBox extends Box
{
    use FakeParentCalls, FakeConjures;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
    }
}

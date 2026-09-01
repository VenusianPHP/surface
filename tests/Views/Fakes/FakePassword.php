<?php

namespace Venusian\Surface\Tests\Views\Fakes;

use Surface\NativeWindows\Views\ParentView;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Password;

final class FakePassword extends Password
{
    use FakeControlCalls, FakeEntryCalls;

    public function __construct(
        public readonly CallLog $log,
        int $pointer,
        string $nickname,
        ?ParentView $parent,
        Frame $frame,
        string $text,
    ) {
        parent::__construct($pointer, $nickname, $parent, $frame);
        $this->stored = $text;
    }
}

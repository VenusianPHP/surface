<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\TextArea;

/** A TextArea whose engine hooks record instead of touching a toolkit. */
final class FakeTextArea extends TextArea
{
    use RecordsTextStyle;
    use RecordsViewApplies;

    /** @var list<string> */
    public array $applied_values = [];

    /** @var list<bool> */
    public array $applied_editable = [];

    protected function applyValue(string $value): void
    {
        $this->applied_values[] = $value;
    }

    protected function applyEditable(bool $editable): void
    {
        $this->applied_editable[] = $editable;
    }

    /** Test door: what the engine's buffer-changed callback does. */
    public function edit(string $value): void
    {
        $this->fireChanged($value);
    }
}

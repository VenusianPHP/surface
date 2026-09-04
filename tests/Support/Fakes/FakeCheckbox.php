<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\Checkbox;

/** A Checkbox whose engine hooks record instead of touching a toolkit. */
final class FakeCheckbox extends Checkbox
{
    use RecordsTextStyle;
    use RecordsViewApplies;

    /** @var list<string> */
    public array $applied_labels = [];

    /** @var list<bool> */
    public array $applied_checked = [];

    /** @var list<bool> */
    public array $applied_enabled = [];

    protected function applyLabel(string $label): void
    {
        $this->applied_labels[] = $label;
    }

    protected function applyChecked(bool $checked): void
    {
        $this->applied_checked[] = $checked;
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
    }

    /** Test door: what the engine's native toggle callback does. */
    public function tick(bool $checked): void
    {
        $this->fireToggled($checked);
    }
}

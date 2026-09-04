<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\Dropdown;

/** A Dropdown whose engine hooks record instead of touching a toolkit. */
final class FakeDropdown extends Dropdown
{
    use RecordsViewApplies;

    /** @var list<array{list<string>, int}> */
    public array $applied_options = [];

    /** @var list<int> */
    public array $applied_selected = [];

    /** @var list<bool> */
    public array $applied_enabled = [];

    protected function applyOptions(array $options, int $selected): void
    {
        $this->applied_options[] = [$options, $selected];
    }

    protected function applySelected(int $selected): void
    {
        $this->applied_selected[] = $selected;
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
    }

    /** Test door: what the engine's native selection callback does. */
    public function pick(int $index): void
    {
        $this->fireSelected($index);
    }
}

<?php

namespace Venusian\Surface\Tests\Support\Fakes;

use Surface\NativeWindows\Views\Table;

/** A Table whose engine hooks record instead of touching a toolkit. */
final class FakeTable extends Table
{
    use RecordsViewApplies;

    /** @var list<list<string>> */
    public array $applied_columns = [];

    /** @var list<list<list<string>>> */
    public array $applied_rows = [];

    /** @var list<int> */
    public array $applied_selected = [];

    /** @var list<bool> */
    public array $applied_enabled = [];

    protected function applyColumns(array $columns): void
    {
        $this->applied_columns[] = $columns;
    }

    protected function applyRows(array $rows): void
    {
        $this->applied_rows[] = $rows;
    }

    protected function applySelectedRow(int $selected): void
    {
        $this->applied_selected[] = $selected;
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->applied_enabled[] = $enabled;
    }

    /** Test door: what the engine's native row-selected callback does. */
    public function pickRow(int $row): void
    {
        $this->fireSelected($row);
    }
}

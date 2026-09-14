<?php

namespace Surface\NativeWindows\Components;

use Surface\Contracts\NativeWindows\Views\OSGroup;
use Surface\Contracts\NativeWindows\Views\OSTable;
use Surface\NativeWindows\Windowable;

/**
 * A thin Component wrap over a table. Headers, rows, and the select
 * hook delegate to the inner table.
 *
 * Parts: `table`.
 */
class DataTable extends Component
{
    /**
     * @param list<string> $columns
     * @param list<list<string>> $rows
     */
    public function __construct(
        Windowable $window,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        protected array $columns,
        protected array $rows = [],
        ?OSGroup $in = null,
    ) {
        parent::__construct($window, $name, $x, $y, $width, $height, $in);
    }

    protected function build(): void
    {
        $this->register('table', $this->root->table(
            $this->partName('table'),
            $this->columns,
            $this->rows,
            0,
            0,
            1,
            1,
        ));
    }

    protected function layout(): void
    {
        [$width, $height] = $this->innerSize();
        $this->parts['table']->place(0, 0, $width, $height);
    }

    /** The wrapped table, for anything not delegated below. */
    public function table(): OSTable
    {
        /** @var OSTable */
        return $this->parts['table'];
    }

    /** @return list<string> */
    public function columns(): array
    {
        return $this->table()->columns();
    }

    /** @param list<string> $columns */
    public function setColumns(array $columns): static
    {
        $this->table()->setColumns($columns);

        return $this;
    }

    /** @return list<list<string>> */
    public function rows(): array
    {
        return $this->table()->rows();
    }

    /** @param list<list<string>> $rows */
    public function setRows(array $rows): static
    {
        $this->table()->setRows($rows);

        return $this;
    }

    public function selectedRow(): int
    {
        return $this->table()->selectedRow();
    }

    /** @return list<string>|null */
    public function selectedCells(): ?array
    {
        return $this->table()->selectedCells();
    }

    public function selectRow(int $row): static
    {
        $this->table()->selectRow($row);

        return $this;
    }

    public function onSelect(callable $hook): static
    {
        $this->table()->onSelect($hook);

        return $this;
    }
}

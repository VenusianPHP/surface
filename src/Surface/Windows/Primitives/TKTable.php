<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TableColumn;
use Surface\Contracts\Windows\Primitives\TKTable as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * Rows are stored normalised: one string per column, in column order, a missing key as ''.
 * Every applyRows() is followed by applySelectedRow() with the current selection, so the
 * native never keeps a selection Surface dropped (or drops one Surface kept). The driver
 * posts RowSelected from the native signal, right after nativeRowSelected().
 */
abstract class TKTable extends TKPrimitive implements PrimitiveContract
{
    /**
     * @var list<TableColumn>
     */
    protected array $columns;

    /**
     * @var list<array<string, string>>
     */
    protected array $rows = [];

    protected ?int $selected_row = null;

    /**
     * @param list<TableColumn> $columns
     * @param list<array<string, scalar|null>> $rows
     * @throws WindowException When the name is not valid, the columns are not a list of TableColumn with unique ids, or a cell is not scalar or null.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        array $columns,
        array $rows,
    ) {
        $this->columns = self::guardColumns($columns);
        $this->rows = array_map($this->normalise(...), array_values($rows));
        parent::__construct($name, $window, $parent, $placement);
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function rows(): array
    {
        return $this->rows;
    }

    public function setRows(array $rows): static
    {
        $this->live();
        $this->rows = array_map($this->normalise(...), array_values($rows));
        $this->selected_row = null;
        $this->reload();

        return $this;
    }

    public function appendRow(array $row): static
    {
        $this->live();
        $this->rows[] = $this->normalise($row);
        $this->reload();

        return $this;
    }

    public function clearRows(): static
    {
        return $this->setRows([]);
    }

    public function selectedRow(): ?int
    {
        return $this->selected_row;
    }

    public function selectRow(?int $row): static
    {
        $this->live();
        if (! is_null($row) && ($row < 0 || $row >= count($this->rows))) {
            throw new WindowException("Row {$row} is out of range (".count($this->rows).' rows).');
        }
        $this->selected_row = $row;
        $this->applySelectedRow($row);

        return $this;
    }

    /**
     * Engine callback: record the user's selection, post nothing, write nothing back.
     *
     * @param int|null $row
     * @return void
     */
    public function nativeRowSelected(?int $row): void
    {
        $this->selected_row = $row;
    }

    /**
     * The rows as the engine shows them: one string per column, in column order.
     * Drivers build the native from this and receive it again in applyRows().
     *
     * @return list<list<string>>
     */
    protected function cellRows(): array
    {
        return array_map(array_values(...), $this->rows);
    }

    protected function reload(): void
    {
        $this->applyRows($this->cellRows());
        $this->applySelectedRow($this->selected_row);
    }

    /**
     * @param array<array-key, mixed> $row
     * @return array<string, string>
     * @throws WindowException When a cell is not scalar or null.
     */
    protected function normalise(array $row): array
    {
        $normalised = [];
        foreach ($this->columns as $column) {
            $cell = $row[$column->id] ?? null;
            if (! is_null($cell) && ! is_scalar($cell)) {
                throw new WindowException("Table cell '{$column->id}' must be scalar or null, got ".get_debug_type($cell).'.');
            }
            $normalised[$column->id] = (string) $cell;
        }

        return $normalised;
    }

    /**
     * @param array<mixed> $columns
     * @return list<TableColumn>
     * @throws WindowException
     */
    protected static function guardColumns(array $columns): array
    {
        if (! array_is_list($columns) || array_filter($columns, fn (mixed $column): bool => ! $column instanceof TableColumn) !== []) {
            throw new WindowException('Table columns must be a list of TableColumn.');
        }
        $ids = array_map(fn (TableColumn $column): string => $column->id, $columns);
        if (count($ids) !== count(array_unique($ids))) {
            throw new WindowException('Table column ids must be unique, got '.implode(', ', $ids).'.');
        }

        return $columns;
    }

    /**
     * Replace every native row.
     *
     * @param list<list<string>> $cells
     * @return void
     */
    abstract protected function applyRows(array $cells): void;

    /**
     * @param int|null $row null clears the selection
     * @return void
     */
    abstract protected function applySelectedRow(?int $row): void;
}

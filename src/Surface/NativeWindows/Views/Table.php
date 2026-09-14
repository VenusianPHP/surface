<?php

namespace Surface\NativeWindows\Views;

use Closure;
use Surface\Contracts\Core\Events\SurfaceEventType;
use Surface\Contracts\NativeWindows\Views\OSTable;
use Surface\NativeWindows\Windowable;

/**
 * A read-only table of string cells. Engines wire their native row
 * selection into fireSelected(); every pick rides the dock as
 * ROW_SELECTED (`<window>.<name>.selected`).
 */
abstract class Table extends View implements OSTable
{
    use HasEnabledState;

    protected ?Closure $on_select = null;

    /**
     * @param list<string> $columns
     * @param list<list<string>> $rows
     */
    public function __construct(
        string $name,
        Windowable $window,
        protected array $columns,
        protected array $rows,
        protected int $selected = -1,
    ) {
        parent::__construct($name, $window);
        $this->columns = array_values($columns);
        $this->rows = $this->normalizeRows($rows);
        $this->selected = $selected < 0 ? -1 : $this->clampRow($selected);
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function setColumns(array $columns): static
    {
        $this->columns = array_values($columns);
        $this->applyColumns($this->columns);

        return $this;
    }

    public function rows(): array
    {
        return $this->rows;
    }

    public function setRows(array $rows): static
    {
        $this->rows = $this->normalizeRows($rows);
        $this->applyRows($this->rows);
        if ($this->selected !== -1) {
            $this->selected = -1;
            $this->applySelectedRow(-1);
        }

        return $this;
    }

    public function selectedRow(): int
    {
        return $this->selected;
    }

    public function selectedCells(): ?array
    {
        return $this->rows[$this->selected] ?? null;
    }

    public function selectRow(int $row): static
    {
        $row = $this->clampRow($row);
        if ($this->selected !== $row) {
            $this->selected = $row;
            $this->applySelectedRow($row);
        }

        return $this;
    }

    public function onSelect(callable $hook): static
    {
        $this->on_select = $hook(...);

        return $this;
    }

    /**
     * Engine callbacks land here with the row read from the native
     * control. Clamps, -1 when empty. Pushes ROW_SELECTED, then invokes
     * the hook with (row, cells); safe with no hook and no sink.
     * @return void
     */
    protected function fireSelected(int $row): void
    {
        $this->selected = $this->clampRow($row);
        $cells = $this->selectedCells() ?? [];
        $this->window->emitViewEvent(SurfaceEventType::ROW_SELECTED, $this->name, [
            'row' => $this->selected,
            'cells' => $cells,
        ]);

        if (! is_null($this->on_select)) {
            ($this->on_select)($this->selected, $cells);
        }
    }

    protected function clampRow(int $row): int
    {
        if ($this->rows === []) {
            return -1;
        }

        return max(0, min(count($this->rows) - 1, $row));
    }

    /**
     * @param list<list<string>> $rows
     * @return list<list<string>>
     */
    protected function normalizeRows(array $rows): array
    {
        return array_values(array_map(
            static fn (array $cells): array => array_values($cells),
            $rows,
        ));
    }

    /**
     * Rebuild the native headers.
     * @param list<string> $columns
     * @return void
     */
    abstract protected function applyColumns(array $columns): void;

    /**
     * Rebuild the native rows.
     * @param list<list<string>> $rows
     * @return void
     */
    abstract protected function applyRows(array $rows): void;

    /**
     * Write the selection to the native control. -1 means none.
     * @return void
     */
    abstract protected function applySelectedRow(int $selected): void;
}

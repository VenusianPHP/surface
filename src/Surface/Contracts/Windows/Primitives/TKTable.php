<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Rows of text under fixed columns; rows are keyed by column id, a missing key
 * shows ''. Posts RowSelected.
 */
interface TKTable extends TKPrimitive
{
    /**
     * @return list<TableColumn>
     */
    public function columns(): array;

    /**
     * Every row as column id => cell text, one key per column.
     * @return list<array<string, string>>
     */
    public function rows(): array;

    /**
     * Replace every row; clears the selection.
     * @param list<array<string, scalar|null>> $rows
     * @return $this
     */
    public function setRows(array $rows): static;

    /**
     * @param array<string, scalar|null> $row
     * @return $this
     */
    public function appendRow(array $row): static;

    /**
     * Drop every row; clears the selection.
     * @return $this
     */
    public function clearRows(): static;

    /**
     * @return int|null
     */
    public function selectedRow(): ?int;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param int|null $row null clears the selection
     * @return $this
     * @throws WindowException When the row is outside 0..count(rows)-1.
     */
    public function selectRow(?int $row): static;
}

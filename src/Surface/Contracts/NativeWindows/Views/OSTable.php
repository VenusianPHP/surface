<?php

namespace Surface\Contracts\NativeWindows\Views;

/**
 * A read-only table of string cells with headers and single-row selection.
 */
interface OSTable extends OSView
{
    /** @return list<string> */
    public function columns(): array;

    /** Replace the headers wholesale. */
    public function setColumns(array $columns): static;

    /** @return list<list<string>> */
    public function rows(): array;

    /** Replace the data wholesale and clear the selection. */
    public function setRows(array $rows): static;

    public function selectedRow(): int;

    /** @return list<string>|null */
    public function selectedCells(): ?array;

    /** Programmatic write — silent; does not fire the hook or mail. */
    public function selectRow(int $row): static;

    /** Hook invoked when the user picks a row, during the pump. Receives (int $row, list<string> $cells). */
    public function onSelect(callable $hook): static;

    public function setEnabled(bool $enabled): static;

    public function isEnabled(): bool;
}

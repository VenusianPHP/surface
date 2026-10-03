<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * One table column: rows are keyed by $id, the header shows $label.
 */
readonly class TableColumn
{
    public function __construct(
        public string $id,
        public string $label,
    ) {}
}

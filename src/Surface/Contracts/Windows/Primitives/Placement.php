<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Where a child goes inside its container: nothing for a column, row or scroll view
 * (order is the rule), a cell for a grid, a frame for a fixed.
 */
readonly class Placement
{
    private function __construct(
        public ?int $row = null,
        public ?int $column = null,
        public int $rowSpan = 1,
        public int $columnSpan = 1,
        public ?int $x = null,
        public ?int $y = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    /**
     * The next slot in creation order.
     * @return self
     */
    public static function next(): self
    {
        return new self();
    }

    /**
     * A grid cell, spanning $rowSpan rows and $columnSpan columns from (row, column).
     *
     * @param int $row
     * @param int $column
     * @param int $rowSpan
     * @param int $columnSpan
     * @return self
     * @throws WindowException When row/column are negative or a span is below 1.
     */
    public static function cell(int $row, int $column, int $rowSpan = 1, int $columnSpan = 1): self
    {
        if ($row < 0 || $column < 0) {
            throw new WindowException("A grid cell needs row and column >= 0, got {$row},{$column}.");
        }
        if ($rowSpan < 1 || $columnSpan < 1) {
            throw new WindowException("A grid span needs rows and columns >= 1, got {$rowSpan}x{$columnSpan}.");
        }

        return new self(row: $row, column: $column, rowSpan: $rowSpan, columnSpan: $columnSpan);
    }

    /**
     * A pixel frame inside a fixed container.
     *
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @return self
     * @throws WindowException When width or height is below 1.
     */
    public static function frame(int $x, int $y, int $width, int $height): self
    {
        if ($width < 1 || $height < 1) {
            throw new WindowException("A frame needs width and height >= 1, got {$width}x{$height}.");
        }

        return new self(x: $x, y: $y, width: $width, height: $height);
    }
}

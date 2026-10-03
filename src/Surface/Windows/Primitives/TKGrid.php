<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKGrid as PrimitiveContract;
use Surface\Contracts\Windows\Primitives\TKPrimitive as Child;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * Children in cells: at() sets the cell the next creation call takes; every covered
 * cell holds one child.
 */
abstract class TKGrid extends TKGroup implements PrimitiveContract
{
    /**
     * @var array<string, string> "row,column" => child name, for every covered cell
     */
    protected array $cells = [];

    /**
     * @param string $name
     * @param ToolkitWindow $window
     * @param TKPrimitiveGroup|null $parent
     * @param Placement $placement
     * @param int $spacing between cells
     * @param int $padding around the cells
     * @throws WindowException When the name is not valid, or spacing or padding is negative.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected int $spacing,
        protected readonly int $padding,
    ) {
        if ($spacing < 0 || $padding < 0) {
            throw new WindowException("Spacing and padding must be >= 0, got {$spacing} and {$padding}.");
        }
        parent::__construct($name, $window, $parent, $placement);
    }

    public function at(int $row, int $column, int $rowSpan = 1, int $columnSpan = 1): static
    {
        $this->live();
        $this->pending = Placement::cell($row, $column, $rowSpan, $columnSpan);

        return $this;
    }

    public function spacing(): int
    {
        return $this->spacing;
    }

    public function setSpacing(int $spacing): static
    {
        $this->live();
        if ($spacing < 0) {
            throw new WindowException("Spacing must be >= 0, got {$spacing}.");
        }
        $this->spacing = $spacing;
        $this->applySpacing($spacing);

        return $this;
    }

    public function forgetChild(Child $child): void
    {
        parent::forgetChild($child);
        $this->cells = array_filter($this->cells, fn (string $holder): bool => $holder !== $child->name());
    }

    protected function defaultPlacement(): Placement
    {
        throw new WindowException("Grid '{$this->path()}' needs at(row, column) before creating a child.");
    }

    protected function admitChild(string $name, bool $group, Placement $placement): void
    {
        foreach ($this->covered($placement) as $cell) {
            if (isset($this->cells[$cell])) {
                throw new WindowException("Cell {$cell} of '{$this->path()}' is taken by '{$this->cells[$cell]}'.");
            }
        }
    }

    protected function adopt(Child $child): Child
    {
        parent::adopt($child);
        foreach ($this->covered($child->placement()) as $cell) {
            $this->cells[$cell] = $child->name();
        }

        return $child;
    }

    /**
     * @param Placement $placement
     * @return list<string> "row,column" for every cell the placement spans
     */
    protected function covered(Placement $placement): array
    {
        $cells = [];
        for ($row = $placement->row; $row < $placement->row + $placement->rowSpan; $row++) {
            for ($column = $placement->column; $column < $placement->column + $placement->columnSpan; $column++) {
                $cells[] = "{$row},{$column}";
            }
        }

        return $cells;
    }

    /**
     * @param int $spacing
     * @return void
     */
    abstract protected function applySpacing(int $spacing): void;
}

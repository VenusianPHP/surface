<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKColumn as PrimitiveContract;
use Surface\Contracts\Windows\Primitives\TKPrimitive as Child;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * Children stacked along the column's axis in display order. A child reorders itself
 * (TKPrimitive::moveBefore/moveAfter/moveTo), which lands in positionOf() and reorder() here.
 */
abstract class TKColumn extends TKGroup implements PrimitiveContract
{
    /**
     * @param string $name
     * @param ToolkitWindow $window
     * @param TKPrimitiveGroup|null $parent
     * @param Placement $placement
     * @param int $spacing between children
     * @param int $padding around the children
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

    protected function defaultPlacement(): Placement
    {
        return Placement::next();
    }

    /**
     * Where $sibling sits once $child is lifted out. For TKPrimitive::moveBefore()/moveAfter().
     *
     * @param Child $sibling
     * @param Child $child
     * @return int
     * @throws WindowException When either is not a child here, or they are the same.
     */
    protected function positionOf(Child $sibling, Child $child): int
    {
        $this->live();
        $this->own($child);
        $this->own($sibling);
        if ($sibling === $child) {
            throw new WindowException("Cannot move '{$child->path()}' relative to itself.");
        }

        return array_search($sibling->name(), array_keys(array_diff_key($this->children, [$child->name() => true])), true);
    }

    /**
     * Put $child at display position $index (already in range) and tell the engine.
     *
     * @param Child $child
     * @param int $index
     * @return $this
     */
    protected function reorder(Child $child, int $index): static
    {
        $others = array_values(array_diff_key($this->children, [$child->name() => true]));
        array_splice($others, $index, 0, [$child]);
        $this->children = array_combine(array_map(fn (Child $each): string => $each->name(), $others), $others);
        $this->applyOrder($child, $index);

        return $this;
    }

    /**
     * @param int $spacing
     * @return void
     */
    abstract protected function applySpacing(int $spacing): void;

    /**
     * Move $child's native to display position $index.
     *
     * @param Child $child
     * @param int $index
     * @return void
     */
    abstract protected function applyOrder(Child $child, int $index): void;
}

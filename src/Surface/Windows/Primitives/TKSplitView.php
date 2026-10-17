<?php

namespace Surface\Windows\Primitives;

use Surface\Contracts\Windows\Primitives\Placement;
use Surface\Contracts\Windows\Primitives\TKPrimitive as Child;
use Surface\Contracts\Windows\Primitives\TKSplitView as PrimitiveContract;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;

/**
 * Each child is a pane, in the order added. Positions are fractions of the split's length, one per
 * divider between visible panes; the native holds them, so positions() follows the user's drags.
 */
abstract class TKSplitView extends TKPrimitiveGroup implements PrimitiveContract
{
    /**
     * @throws WindowException When the name is not valid.
     */
    public function __construct(
        string $name,
        ToolkitWindow $window,
        ?TKPrimitiveGroup $parent,
        Placement $placement,
        protected readonly bool $horizontal,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function isHorizontal(): bool
    {
        return $this->horizontal;
    }

    public function positions(): array
    {
        $this->live();

        return $this->dividers() === 0 ? [] : $this->nativePositions();
    }

    public function setPositions(array $positions): static
    {
        $this->live();
        $dividers = $this->dividers();
        if (! array_is_list($positions) || count($positions) !== $dividers) {
            throw new WindowException("Split '{$this->path()}' has {$dividers} divider(s); give one position each, got ".count($positions).'.');
        }
        $last = 0.0;
        foreach ($positions as $position) {
            if (! is_float($position) && ! is_int($position)) {
                throw new WindowException('Split positions are fractions, got '.get_debug_type($position).'.');
            }
            if ($position < 0.0 || $position > 1.0 || $position < $last) {
                throw new WindowException('Split positions ascend within 0..1, got ['.implode(', ', $positions).'].');
            }
            $last = (float) $position;
        }
        $this->applyPositions(array_map(fn (float|int $p): float => (float) $p, $positions));

        return $this;
    }

    /**
     * Dividers between visible panes.
     * @return int
     */
    protected function dividers(): int
    {
        $visible = count(array_filter($this->children, fn (Child $child): bool => $child->isVisible()));

        return max(0, $visible - 1);
    }

    protected function defaultPlacement(): Placement
    {
        return Placement::next();
    }

    /**
     * @return list<float>
     */
    abstract protected function nativePositions(): array;

    /**
     * @param list<float> $positions already checked: one per divider, ascending, within 0..1
     * @return void
     */
    abstract protected function applyPositions(array $positions): void;
}

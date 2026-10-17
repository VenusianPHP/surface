<?php

namespace Surface\Contracts\Windows\Primitives;

/**
 * Panes in order with a draggable divider between each visible pair. A hidden pane collapses and
 * the visible panes share the length; panes keep their minSize().
 */
interface TKSplitView extends TKPrimitiveGroup
{
    /**
     * @return bool true: panes side by side, dividers vertical
     */
    public function isHorizontal(): bool;

    /**
     * Each divider between visible panes as a fraction of the split's length, as the native has it now.
     * @return list<float>
     */
    public function positions(): array;

    /**
     * One fraction per divider between visible panes, ascending, each in 0..1.
     * @param list<float> $positions
     * @return $this
     */
    public function setPositions(array $positions): static;
}

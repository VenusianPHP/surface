<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\WindowException;

/**
 * Nodes under fixed columns, the first column holding the expander. One selection, one scroll and one
 * expansion state. Expansion is recorded per node: a node keeps its state while an ancestor is collapsed
 * and shows it when the ancestor opens. Posts NodeSelected, NodeActivated and NodeExpanded for the
 * user's actions only.
 */
interface TKTree extends TKPrimitive
{
    /**
     * @return list<TableColumn> the first holds the expander
     */
    public function columns(): array;

    /**
     * Replace every node. Expansion is kept for ids still present with children, the selection when its
     * id is still present. Posts nothing.
     * @param list<TreeNode> $nodes
     * @return $this
     * @throws WindowException When an id appears twice; nothing changes then.
     */
    public function setNodes(array $nodes): static;

    /**
     * @return list<TreeNode>
     */
    public function nodes(): array;

    /**
     * A leaf does nothing; a node under a collapsed ancestor is recorded and opens with it. Posts nothing.
     * @param string $id
     * @param bool $expanded
     * @return $this
     * @throws WindowException When there is no such node.
     */
    public function expand(string $id, bool $expanded = true): static;

    /**
     * @param string $id
     * @return bool
     * @throws WindowException When there is no such node.
     */
    public function isExpanded(string $id): bool;

    /**
     * @return string|null the selected node as the native has it now
     */
    public function selectedNode(): ?string;

    /**
     * Expands the ancestors and scrolls the node into view. Posts nothing.
     * @param string|null $id null clears the selection
     * @return $this
     * @throws WindowException When there is no such node.
     */
    public function selectNode(?string $id): static;
}

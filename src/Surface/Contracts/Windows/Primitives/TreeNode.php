<?php

namespace Surface\Contracts\Windows\Primitives;

use InvalidArgumentException;
use Surface\NutsAndBolts\Color;

/**
 * One row of a tree: its cells by column id, its children in order, and a text colour per cell.
 * A node with children shows an expander; a leaf never does.
 */
final readonly class TreeNode
{
    /**
     * @param string $id unique in the tree
     * @param array<string, string> $cells column id => text; a missing column shows ''
     * @param list<TreeNode> $children
     * @param array<string, Color> $colors column id => text colour
     * @throws InvalidArgumentException When the id is empty, a cell is not string => string, the children are not a list of TreeNode, or a colour is not a Color.
     */
    public function __construct(
        public string $id,
        public array $cells,
        public array $children = [],
        public array $colors = [],
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('A tree node id is not empty.');
        }
        foreach ($cells as $column => $text) {
            if (! is_string($column) || ! is_string($text)) {
                throw new InvalidArgumentException("Tree node '{$id}' cells are column id => string.");
            }
        }
        if (! array_is_list($children) || array_filter($children, fn (mixed $child): bool => ! $child instanceof TreeNode) !== []) {
            throw new InvalidArgumentException("Tree node '{$id}' children are a list of TreeNode.");
        }
        foreach ($colors as $color) {
            if (! $color instanceof Color) {
                throw new InvalidArgumentException("Tree node '{$id}' colours are column id => Color.");
            }
        }
    }
}

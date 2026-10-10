<?php

namespace Surface\Contracts\Windows\Primitives;

use Surface\Contracts\Windows\Menus\ContextMenu;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;

/**
 * A native view inside a toolkit window. The toolkit owns layout; PHP declares
 * properties and reads sizes back. Every setter throws WindowException once removed.
 */
interface TKPrimitive
{
    /**
     * The name, unique among its siblings.
     * @return string
     */
    public function name(): string;

    /**
     * The dotted path from the window's content container, e.g. `main.toolbar.save`.
     * @return string
     */
    public function path(): string;

    /**
     * @return string uuid4
     */
    public function uuid(): string;

    /**
     * @return ToolkitWindow
     */
    public function window(): ToolkitWindow;

    /**
     * The container this primitive was created in; null for the content container.
     * @return TKPrimitiveGroup|null
     */
    public function parent(): ?TKPrimitiveGroup;

    /**
     * Where this primitive sits in its container: next, a grid cell, or its creation frame.
     * @return Placement
     */
    public function placement(): Placement;

    /**
     * @return bool
     */
    public function isRemoved(): bool;

    /**
     * Terminal: destroy the native and the subtree, free the names and uuids.
     * @return void
     * @throws WindowException When already removed.
     */
    public function remove(): void;

    /**
     * Native visibility; hiding a container hides its subtree.
     * @param bool $visible
     * @return $this
     */
    public function setVisible(bool $visible): static;

    /**
     * @return bool
     */
    public function isVisible(): bool;

    /**
     * @return $this
     */
    public function show(): static;

    /**
     * @return $this
     */
    public function hide(): static;

    /**
     * Native sensitivity; containers cascade to their subtree.
     * @param bool $enabled
     * @return $this
     */
    public function setEnabled(bool $enabled): static;

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * @return $this
     */
    public function enable(): static;

    /**
     * @return $this
     */
    public function disable(): static;

    /**
     * The menu the toolkit opens where this view is right-clicked: a menu's nodes in the menu
     * profile's item format, a parsed menu (one shared by many views), or null to take it off.
     * A chosen item posts MenuActivated with its id.
     * @param array|ContextMenu|null $menu
     * @return $this
     */
    public function setContextMenu(array|ContextMenu|null $menu): static;

    public function contextMenu(): ?ContextMenu;

    /**
     * The current allocation, read from the toolkit.
     * @return array{int, int} width, height
     */
    public function size(): array;

    /**
     * Opt in to (or out of) ViewResized mail for this view.
     * @param bool $on
     * @return $this
     */
    public function watchSize(bool $on = true): static;

    /**
     * @return bool
     */
    public function isWatchingSize(): bool;

    /**
     * Fill behind the view; null restores the toolkit's own.
     * @param Color|null $color
     * @return $this
     */
    public function setBackground(?Color $color): static;

    /**
     * Expand into spare space along each axis.
     * @param bool $horizontal
     * @param bool $vertical
     * @return $this
     */
    public function fill(bool $horizontal = true, bool $vertical = true): static;

    /**
     * Placement inside a slot larger than the view.
     * @param Align $horizontal
     * @param Align $vertical
     * @return $this
     */
    public function align(Align $horizontal, Align $vertical = Align::FILL): static;

    /**
     * The floor the toolkit never shrinks the view below.
     * @param int $width
     * @param int $height
     * @return $this
     * @throws WindowException When either is negative.
     */
    public function minSize(int $width, int $height): static;

    /**
     * Move this primitive just before $sibling inside their column or row.
     * @param TKPrimitive $sibling
     * @return $this
     * @throws WindowException When the parent is not a column or row, $sibling is not a sibling, or it is this primitive.
     */
    public function moveBefore(TKPrimitive $sibling): static;

    /**
     * Move this primitive just after $sibling inside their column or row.
     * @param TKPrimitive $sibling
     * @return $this
     * @throws WindowException When the parent is not a column or row, $sibling is not a sibling, or it is this primitive.
     */
    public function moveAfter(TKPrimitive $sibling): static;

    /**
     * Move this primitive to display position $index inside its column or row.
     * @param int $index the position afterward, 0..count(children)-1
     * @return $this
     * @throws WindowException When the parent is not a column or row, or the index is out of range.
     */
    public function moveTo(int $index): static;
}

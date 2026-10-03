<?php

namespace Surface\Contracts\Windows;

use Surface\Contracts\Windows\Primitives\PrimitiveFactory;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKFixed;
use Surface\Contracts\Windows\Primitives\TKGrid;
use Surface\Contracts\Windows\Primitives\TKPrimitive;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\PrimitiveRegistry;

interface ToolkitWindow extends OSWindow
{
    public function name(): string;

    /**
     *  Destroy the native window. Terminal: the handle is dead afterward and
     *  WindowClosed is posted, exactly as when the user closes it.
     *
     * @return void
     */
    public function close(): void;

    /**
     * @return bool
     */
    public function isOpen(): bool;

    /**
     * @return string
     */
    public function title(): string;

    /**
     * Show and focus. The first present maps the window.
     *
     * @return $this
     */
    public function present(): static;

    /**
     * @param string $item
     * @return bool
     */
    public function isToggled(string $item): bool;

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): static;

    /**
     * @param string $profile
     * @return $this
     * @throws WindowException When no profile is registered under that name.
     */
    public function setMenuBar(string $profile): static;

    /**
     * Flip a toggle item's state. Posts nothing: the app changed it, it knows.
     *
     * @param string $item
     * @param bool $on
     * @return $this
     */
    public function setToggle(string $item, bool $on): static;
    /**
     * Declare the window's one content container as a column. Every other primitive is
     * created on a container.
     *
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKColumn
     * @throws WindowException When the window already has content, is closed, or the name is invalid.
     */
    public function column(string $name, int $spacing = 0, int $padding = 0): TKColumn;

    /**
     * Declare the window's one content container as a row.
     *
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKRow
     * @throws WindowException When the window already has content, is closed, or the name is invalid.
     */
    public function row(string $name, int $spacing = 0, int $padding = 0): TKRow;

    /**
     * Declare the window's one content container as a grid.
     *
     * @param string $name
     * @param int $spacing
     * @param int $padding
     * @return TKGrid
     * @throws WindowException When the window already has content, is closed, or the name is invalid.
     */
    public function grid(string $name, int $spacing = 0, int $padding = 0): TKGrid;

    /**
     * Declare the window's one content container as a fixed (pixel-framed) group.
     *
     * @param string $name
     * @return TKFixed
     * @throws WindowException When the window already has content, is closed, or the name is invalid.
     */
    public function fixed(string $name): TKFixed;

    /**
     * The one content container, once declared.
     *
     * @return TKPrimitiveGroup|null
     * @throws WindowException When the window is closed.
     */
    public function content(): ?TKPrimitiveGroup;

    /**
     * Look a primitive up by dotted path from the content container: `main.toolbar.save`.
     *
     * @param string $path
     * @return TKPrimitive|null
     * @throws WindowException When the window is closed.
     */
    public function view(string $path): ?TKPrimitive;

    /**
     * Look a primitive up by uuid.
     *
     * @param string $uuid
     * @return TKPrimitive|null
     * @throws WindowException When the window is closed.
     */
    public function uuid(string $uuid): ?TKPrimitive;

    /**
     * The content area, read from the toolkit.
     *
     * @return array{int, int} width, height
     * @throws WindowException When the window is closed.
     */
    public function size(): array;

    /**
     * This window's path and uuid index. For primitives and their containers.
     *
     * @return PrimitiveRegistry
     */
    public function registry(): PrimitiveRegistry;

    /**
     * The driver's minter. For containers.
     *
     * @return PrimitiveFactory
     */
    public function factory(): PrimitiveFactory;

    /**
     * Clear the content slot. Called by the content container's remove().
     *
     * @param TKPrimitiveGroup $content
     * @return void
     * @throws WindowException When $content is not this window's content or is not removed.
     */
    public function forgetContent(TKPrimitiveGroup $content): void;
}

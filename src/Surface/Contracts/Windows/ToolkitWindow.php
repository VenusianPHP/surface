<?php

namespace Surface\Contracts\Windows;

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
}
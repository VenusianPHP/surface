<?php

namespace Surface\Contracts\Windows;

use Surface\Contracts\Windows\Menus\MenuProfile;

interface ToolkitWindowDriver extends WindowDriver
{
    /**
     * @param string $name
     * @param int $width
     * @param int $height
     * @param MenuProfile|null $menu
     * @return NativeWindow
     * @throws WindowException When the name is taken or the toolkit refuses.
     */
    public function open(string $name, int $width, int $height, ?MenuProfile $menu = null): ToolkitWindow;

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * @param string $name
     * @return ToolkitWindow|null
     */
    public function get(string $name): ?ToolkitWindow;

    /**
     * @return array<string, NativeWindow> name => window
     */
    public function all(): array;

    public function closeAll(): void;

    /**
     * The bar shown when no window of this driver has focus (macOS only; a no-op elsewhere).
     *
     * @param MenuProfile|null $menu
     * @return void
     */
    public function setDefaultMenuBar(?MenuProfile $menu): void;
}
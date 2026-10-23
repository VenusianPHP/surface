<?php

namespace Surface\Contracts\Windows;

use Surface\Contracts\Windows\Menus\MenuProfile;
use Surface\Contracts\Windows\Services\Clipboard;
use Surface\Contracts\Windows\Services\Desktop;
use Surface\Contracts\Windows\Services\Dialogs;

interface ToolkitWindowDriver extends WindowDriver
{
    /**
     * With a parent, the window is a child: modal to the parent, with no menu bar. Closing the
     * parent closes the child first.
     *
     * @param string $name
     * @param int $width
     * @param int $height
     * @param MenuProfile|null $menu
     * @param ToolkitWindow|null $parent
     * @return ToolkitWindow
     * @throws WindowException When the name is taken or the toolkit refuses; and with a parent:
     *                         when the parent is closed, is not a window of this driver, is a
     *                         child, or has an open child, or $menu is not null.
     */
    public function open(string $name, int $width, int $height, ?MenuProfile $menu = null, ?ToolkitWindow $parent = null): ToolkitWindow;

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

    /**
     * Set the state of a node of the default menu bar profile, on every platform. The state shows
     * where a default bar shows (macOS); the driver keeps it for a bar it builds later. A new
     * default profile starts from its own states.
     *
     * @param string $item
     * @param bool $enabled
     * @return void
     * @throws WindowException When no default profile is set, and as ToolkitWindow::setItemEnabled().
     */
    public function setItemEnabled(string $item, bool $enabled): void;

    /**
     * @param string $item
     * @return bool
     * @throws WindowException When no default profile is set, and as ToolkitWindow::isItemEnabled().
     */
    public function isItemEnabled(string $item): bool;

    /** Alerts and file panels; answers come back as mail through the driver's session. */
    public function dialogs(): Dialogs;

    public function clipboard(): Clipboard;

    public function desktop(): Desktop;
}

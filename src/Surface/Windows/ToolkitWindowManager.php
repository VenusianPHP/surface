<?php

namespace Surface\Windows;

use Surface\Bridge\ToolkitManager;
use Surface\Contracts\Windows\Services\Clipboard;
use Surface\Contracts\Windows\Services\Desktop;
use Surface\Contracts\Windows\Services\Dialogs;
use Surface\Contracts\Windows\ToolkitWindow;
use Surface\Contracts\Windows\ToolkitWindowDriver as ToolkitWindowDriverContract;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\MenuProfile;

class ToolkitWindowManager
{
    /**
     * @var array<string, MenuProfile>
     */
    protected array $profiles = [];

    public function __construct(
        protected readonly ToolkitManager $toolkits,
        array $menus,
        protected readonly ?string $default_menu,
    ) {
        foreach ($menus as $name => $nodes)
        {
            $this->profiles[$name] = MenuProfile::parse($name, $nodes);
        }
    }

    /** With a parent, the window is a child of it (ToolkitWindowDriver::open()). */
    public function open(string $name, int $width, int $height, ?string $menu = null, ?ToolkitWindow $parent = null): ToolkitWindow
    {
        $driver = $this->driver();
        $driver->setDefaultMenuBar($this->default_menu === null ? null : $this->profile($this->default_menu));

        return $driver->open($name, $width, $height, $menu === null ? null : $this->profile($menu), $parent);
    }

    public function get(string $name): ?ToolkitWindow { return $this->driver()->get($name); }

    /** @return array<string, ToolkitWindow> */
    public function all(): array
    {
        return $this->driver()->all();
    }

    public function closeAll(): void
    {
        $this->driver()->closeAll();
    }

    /**
     * Set the state of a node of the default menu bar: the driver gets the configured default
     * profile first, as open() gives it.
     *
     * @throws WindowException When config/windows.php names no default menu, and as the driver.
     */
    public function setItemEnabled(string $item, bool $enabled): void
    {
        $this->defaultBarDriver()->setItemEnabled($item, $enabled);
    }

    /** @throws WindowException When config/windows.php names no default menu, and as the driver. */
    public function isItemEnabled(string $item): bool
    {
        return $this->defaultBarDriver()->isItemEnabled($item);
    }

    public function dialogs(): Dialogs { return $this->driver()->dialogs(); }

    public function clipboard(): Clipboard { return $this->driver()->clipboard(); }

    public function desktop(): Desktop { return $this->driver()->desktop(); }

    public function profile(string $name): MenuProfile
    {
        return $this->profiles[$name] ?? throw new WindowException("No menu profile named '{$name}' in config/windows.php.");
    }

    protected function defaultBarDriver(): ToolkitWindowDriverContract
    {
        if (is_null($this->default_menu)) {
            throw new WindowException('config/windows.php names no default menu, so the default bar has no state.');
        }
        $driver = $this->driver();
        $driver->setDefaultMenuBar($this->profile($this->default_menu));

        return $driver;
    }

    protected function driver(): ToolkitWindowDriverContract
    {
        $driver = $this->toolkits->driver();

        return $driver instanceof ToolkitWindowDriverContract
            ? $driver
            : throw new WindowException(get_class($driver).' does not open windows.');
    }
}
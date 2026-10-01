<?php

namespace Surface\Windows;

use Surface\Bridge\ToolkitManager;
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

    public function open(string $name, int $width, int $height, ?string $menu = null): ToolkitWindow
    {
        $driver = $this->driver();
        $driver->setDefaultMenuBar($this->default_menu === null ? null : $this->profile($this->default_menu));

        return $driver->open($name, $width, $height, $menu === null ? null : $this->profile($menu));
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

    public function profile(string $name): MenuProfile
    {
        return $this->profiles[$name] ?? throw new WindowException("No menu profile named '{$name}' in config/windows.php.");
    }

    protected function driver(): ToolkitWindowDriverContract
    {
        $driver = $this->toolkits->driver();

        return $driver instanceof ToolkitWindowDriverContract
            ? $driver
            : throw new WindowException(get_class($driver).' does not open windows.');
    }
}
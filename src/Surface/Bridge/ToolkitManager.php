<?php

namespace Surface\Bridge;

use ReflectionException;
use Voyager\NutsAndBolts\Manager;
use Jovian\Toolkits\Qt\Bridge\QtBridgeDriver;
use Surface\Contracts\Bridge\BridgeException;
use Jovian\Toolkits\GTK\Bridge\GTKBridgeDriver;
use Jovian\Toolkits\Appkit\Bridge\AppkitBridgeDriver;

class ToolkitManager extends Manager
{
    /**
     * @throws ReflectionException
     * @throws BridgeException When jovian/venusian-gtk or ext-gtk is missing.
     */
    public function createGtkDriver(): ToolkitBridgeDriver
    {
        $this->requireToolkit('gtk', GTKBridgeDriver::class, 'jovian/venusian-gtk', 'gtk');

        return new GtkBridgeDriver($this->vessel);
    }

    /**
     * @throws ReflectionException
     * @throws BridgeException When jovian/venusian-qt or ext-qt is missing.
     */
    public function createQtDriver(): ToolkitBridgeDriver
    {
        $this->requireToolkit('qt', QtBridgeDriver::class, 'jovian/venusian-qt', 'qt');

        return new QtBridgeDriver($this->vessel);
    }

    /**
     * @throws ReflectionException
     * @throws BridgeException On Linux, or when jovian/venusian-appkit or ext-appkit is missing.
     */
    public function createAppkitDriver(): ToolkitBridgeDriver
    {
        if(device_os_family() == 'linux') {
            throw new BridgeException("Appkit is not supported on Linux.");
        }

        $this->requireToolkit('appkit', AppkitBridgeDriver::class, 'jovian/venusian-appkit', 'appkit');

        return new AppkitBridgeDriver($this->vessel);
    }

    /**
     * Refuse a toolkit whose driver package is not installed, or whose extension this PHP has not loaded.
     * @param string $toolkit The driver name, as the bridge config spells it.
     * @param class-string $driver The driver class the package provides.
     * @param string $package The Composer package that provides the driver.
     * @param string $extension The PHP extension the driver binds.
     * @return void
     * @throws BridgeException
     */
    protected function requireToolkit(string $toolkit, string $driver, string $package, string $extension): void
    {
        if (! class_exists($driver)) {
            throw new BridgeException("The '{$toolkit}' toolkit needs {$package}: composer require {$package}");
        }

        if (! extension_loaded($extension)) {
            throw new BridgeException("The '{$toolkit}' toolkit needs ext-{$extension} loaded in this PHP (".PHP_BINARY.").");
        }
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): string
    {
        $os = device_os_family();
        return config("bridge.toolkit.{$os}.default", 'gtk');
    }
}
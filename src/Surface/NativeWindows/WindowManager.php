<?php

namespace Surface\NativeWindows;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Surface\Bridge\BridgeManager;
use Surface\Contracts\Bridge\BridgedOSSession;
use Surface\Contracts\NativeWindows\LinuxOSWindowDriver;
use Surface\Contracts\NativeWindows\MacOSWindowDriver;
use Surface\NativeWindows\Drivers\AppKitWindowDriver;
use Surface\NativeWindows\Drivers\GTKWindowDriver;
use Voyager\Contracts\Vessel\Vessel;
use Voyager\NutsAndBolts\Manager;

class WindowManager extends Manager
{

    public function createMacDriver(): MacOSWindowDriver
    {
        $driver_class = config('windows.drivers.mac.class', AppKitWindowDriver::class);
        $driver_config = config('windows.drivers.mac.args', [
            // @todo - default hydrate when default config is defined
        ]);
        return new $driver_class(
            $driver_config,
        );
    }

    public function createLinuxDriver(): LinuxOSWindowDriver
    {
        $driver_class = config('windows.drivers.linux.class', GTKWindowDriver::class);
        $driver_config = config('windows.drivers.linux.args', [
            // @todo - default hydrate when default config is defined
        ]);

        return new $driver_class(
            $driver_config,
        );
    }

    public function getDefaultDriver()
    {
        return config('windows.default', device_os_family());
    }
}
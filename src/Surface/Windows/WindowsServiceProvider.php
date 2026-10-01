<?php

namespace Surface\Windows;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class WindowsServiceProvider extends ServiceProvider
{
    /**
     * Menu profiles are parsed once, when the manager is first resolved.
     *
     * @return void
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('toolkit-windows', fn (FrameworkCore $app) => new ToolkitWindowManager(
            $app->get('toolkit-bridge'),
            config('windows.menus', []),
            config('windows.default_menu'),
        ));
        $this->app->alias('toolkit-windows', ToolkitWindowManager::class);
    }

    public function boot(): void
    {

    }
}

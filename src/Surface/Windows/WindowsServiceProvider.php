<?php

namespace Surface\Windows;

use ReflectionException;
use Surface\Windows\Primitives\TKCanvas;
use Surface\Windows\StagedWindow;
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

        $this->app->registerSingleton('staged-windows', fn (FrameworkCore $app) => new StagedWindowManager(
            $app->get('toolkit-bridge'),
            config('bridge.stage.'.device_os_family().'.default'),
        ));
        $this->app->alias('staged-windows', StagedWindowManager::class);

        // A canvas makes its framebuffer through the application's FramebufferManager, resolved when first asked.
        TKCanvas::resolveFramebuffersUsing(fn (?string $driver) => $this->app->make('framebuffers')->driver($driver));
        StagedWindow::resolveFramebuffersUsing(fn (?string $driver) => $this->app->make('framebuffers')->driver($driver));
    }

    public function boot(): void
    {

    }
}

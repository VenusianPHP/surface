<?php

namespace Surface\Windows;

use ReflectionException;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\FramebufferException;
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

        // A canvas's format: config, else what the toolkit binds as native. A staged window stays RGBA8.
        TKCanvas::resolveFormatUsing(fn (): FormatSpec => self::canvasFormat(
            config('framebuffers.canvas_format'),
            $this->app->has('framebuffers.native_canvas_format') ? $this->app->make('framebuffers.native_canvas_format') : null,
        ));
    }

    /**
     * framebuffers.canvas_format when set, else the toolkit's framebuffers.native_canvas_format, else 'rgba8'.
     *
     * @throws FramebufferException When the chosen name is not 'rgba8' or 'bgra8'.
     */
    public static function canvasFormat(?string $configured, ?string $native): FormatSpec
    {
        return FormatSpec::named($configured ?? $native ?? 'rgba8');
    }

    public function boot(): void
    {

    }
}

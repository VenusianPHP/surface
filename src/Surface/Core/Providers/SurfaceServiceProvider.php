<?php

namespace Surface\Core\Providers;

use Voyager\IOPools\IOPoolDock;
use Surface\Core\LiveApplication;
use Voyager\Contracts\Vessel\Vessel;
use Surface\Bridge\BridgeServiceProvider;
use Surface\Core\IOPools\OSLevelResourceDriver;
use Surface\Drawing\DrawingServiceProvider;
use Surface\EmbeddedDisplays\EmbeddedDisplaysServiceProvider;
use Surface\Fonts\FontsServiceProvider;
use Surface\Framebuffers\FramebuffersServiceProvider;
use Surface\HumanInput\HumanInputServiceProvider;
use Surface\Stage\StageServiceProvider;
use Voyager\NutsAndBolts\AggregateServiceProvider;
use Surface\NativeWindows\NativeWindowsServiceProvider;

class SurfaceServiceProvider extends AggregateServiceProvider
{
    protected array $providers = [
        BridgeServiceProvider::class,
        FramebuffersServiceProvider::class,
        FontsServiceProvider::class,
        DrawingServiceProvider::class,
        StageServiceProvider::class,
        NativeWindowsServiceProvider::class,
        HumanInputServiceProvider::class,
        EmbeddedDisplaysServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton('live-app', fn ($app) => $app->make(LiveApplication::class));
        $this->app->singleton(OSLevelResourceDriver::class, function (Vessel $app) {
            /** @var IOPoolDock $dock */
            $dock = $this->app->make('io-pool');
            $session = $app->get('os-bridge')->connect();
            $window_service = app('native-window')->driver();
            $driver = new OSLevelResourceDriver($dock, $session, $window_service);

            // 'os' pumps the native events the later resources read, so it
            // ticks first even though it now joins the dock after them.
            $later = $dock->resources()->all();
            foreach (array_keys($later) as $name) {
                $dock->resources()->forget($name);
            }
            $dock->resource('os', $driver);
            foreach ($later as $name => $resource) {
                $dock->resource($name, $resource);
            }

            return $driver;
        });

        // The OS bridge connects here, not at boot: a program that never asks
        // for a LiveApplication — an IC panel, a headless service — never
        // starts NSApplication or GTK.
        $this->app->singleton(LiveApplication::class, function (Vessel $app) {
            $app->make(OSLevelResourceDriver::class);

            return new LiveApplication(
                $app->make('io-pool'),
                $app->get('os-bridge')->connect(),
                app('native-window')->driver(),
                $app->bound('stages') ? $app->make('stages') : null,
                $app->bound('human-input') ? $app->make('human-input') : null,
                $app->bound('embedded-displays') ? $app->make('embedded-displays') : null,
            );
        });
    }
}
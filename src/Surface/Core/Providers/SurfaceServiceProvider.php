<?php

namespace Surface\Core\Providers;

use Surface\Bridge\BridgeServiceProvider;
use Surface\Drawing\DrawingServiceProvider;
use Surface\EmbeddedDisplays\EmbeddedDisplaysServiceProvider;
use Surface\Fonts\FontsServiceProvider;
use Surface\Framebuffers\FramebuffersServiceProvider;
use Surface\Images\ImagesServiceProvider;
use Surface\Rasterize\RasterizeServiceProvider;
use Surface\Windows\WindowsServiceProvider;
use Voyager\NutsAndBolts\AggregateServiceProvider;

class SurfaceServiceProvider extends AggregateServiceProvider
{
    protected array $providers = [
        WindowsServiceProvider::class,
        BridgeServiceProvider::class,
        FramebuffersServiceProvider::class,
        EmbeddedDisplaysServiceProvider::class,
        RasterizeServiceProvider::class,
        ImagesServiceProvider::class,
        FontsServiceProvider::class,
        DrawingServiceProvider::class,
    ];

    /**
     * The package's config files, keyed by the config key each one fills.
     *
     * @return array<string, string>
     */
    protected function configs(): array
    {
        $dir = dirname(__DIR__, 4).'/config';

        return [
            'bridge' => "{$dir}/bridge.php",
            'drawing' => "{$dir}/drawing.php",
            'embedded-displays' => "{$dir}/embedded-displays.php",
            'fonts' => "{$dir}/fonts.php",
            'framebuffers' => "{$dir}/framebuffers.php",
            'images' => "{$dir}/images.php",
            'rasterize' => "{$dir}/rasterize.php",
            'windows' => "{$dir}/windows.php",
        ];
    }

    public function register(): void
    {
        foreach ($this->configs() as $key => $path) {
            $this->mergeConfigFrom($path, $key);
        }

        parent::register();
    }

    public function boot(): void
    {
        $published = [];
        foreach ($this->configs() as $key => $path) {
            $published[$path] = $this->app->configPath("{$key}.php");
        }

        $this->publishes($published, 'surface-config');
    }
}

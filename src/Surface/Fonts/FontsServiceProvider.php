<?php

namespace Surface\Fonts;

use Surface\Contracts\Fonts\FontRegistry;
use Surface\Fonts\Console\FontMakeCommand;
use Voyager\Contracts\Vessel\Vessel;
use Voyager\NutsAndBolts\ServiceProvider;

class FontsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 3).'/config/fonts.php', 'fonts');

        $this->app->singleton(FontManager::class, fn (Vessel $app) => new FontManager($app->make('config')->get('fonts', [])));
        $this->app->alias(FontManager::class, 'fonts');
        $this->app->alias(FontManager::class, FontRegistry::class);
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3).'/config/fonts.php' => $this->app->configPath('fonts.php'),
        ], 'surface-config');

        $this->commands([FontMakeCommand::class]);
    }
}

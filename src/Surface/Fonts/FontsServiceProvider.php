<?php

namespace Surface\Fonts;

use ReflectionException;
use Surface\Contracts\Fonts\FontRegistry;
use Surface\Fonts\Console\FontMakeCommand;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\ServiceProvider;

class FontsServiceProvider extends ServiceProvider
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('fonts', fn (FrameworkCore $app) => new FontManager(
            $app->make('config')->get('fonts', []),
            fn () => $app->make('truetype')->driver(),
        ));
        $this->app->alias('fonts', FontManager::class);
        $this->app->alias('fonts', FontRegistry::class);

        $this->app->registerSingleton('truetype', fn (FrameworkCore $app) => new TrueTypeManager($app));
        $this->app->alias('truetype', TrueTypeManager::class);

        // The console finds a command by its binding, so make:font is bound here.
        $this->app->registerSingleton(FontMakeCommand::class, fn () => new FontMakeCommand(new Filesystem));
        $this->commands([FontMakeCommand::class]);
    }

    public function boot(): void
    {

    }
}

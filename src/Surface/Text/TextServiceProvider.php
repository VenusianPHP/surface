<?php

namespace Surface\Text;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

class TextServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->registerSingleton('text.catalog', fn (FrameworkCore $app) => new FontCatalog());
        $this->app->alias('text.catalog', FontCatalog::class);
        $this->app->registerSingleton('text.faces', fn (FrameworkCore $app) => new FaceCache());
        $this->app->alias('text.faces', FaceCache::class);
        $this->app->registerSingleton('text.shaper', fn (FrameworkCore $app) => new Shaper($app->make('text.catalog'), $app->make('text.faces')));
        $this->app->alias('text.shaper', Shaper::class);
    }

    public function boot(): void {}
}

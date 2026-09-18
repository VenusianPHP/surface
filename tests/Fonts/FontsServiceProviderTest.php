<?php

use Surface\Contracts\Fonts\FontRegistry;
use Surface\Fonts\FontManager;
use Surface\Fonts\FontsServiceProvider;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;

it('merges the config and binds one manager under three names', function () {
    $app = new FakeBindingVessel(['config' => new FakeConfigRepository()]);
    (new FontsServiceProvider($app))->register();

    expect($app->get('config')->get('fonts.default'))->toBe('classic')
        ->and($app->get('config')->get('fonts.faces.classic.enabled'))->toBeTrue()
        ->and($app->bindings[FontManager::class])->toBeInstanceOf(FontManager::class)
        ->and($app->bindings['fonts'])->toBe($app->bindings[FontManager::class])
        ->and($app->bindings[FontRegistry::class])->toBe($app->bindings[FontManager::class])
        ->and($app->bindings['fonts']->has('classic'))->toBeTrue();
});

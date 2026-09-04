<?php

use Surface\Contracts\Core\AboutInfo;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('registers program identity on the application and hands it back', function () {
    [$app] = liveApp();
    $info = new AboutInfo(name: 'Hello Label', version: '0.8.0');

    expect($app->getAbout())->toBeNull()
        ->and($app->setAbout($info))->toBe($app)
        ->and($app->getAbout())->toBe($info);
});

it('presents the registered identity through the engine hook', function () {
    $window = new FakeWindow('main');
    $window->known_about = new AboutInfo(name: 'Hello Label', copyright: '© 2026');

    $window->showAbout();

    expect($window->presented_abouts)->toHaveCount(1)
        ->and($window->presented_abouts[0]->name)->toBe('Hello Label');
});

it('presents the bare panel when nothing is registered', function () {
    $window = new FakeWindow('main');

    $window->showAbout();

    expect($window->presented_abouts)->toBe([null]);
});

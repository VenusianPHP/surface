<?php

use Surface\Contracts\Core\AboutInfo;
use Surface\Core\ProgramShuttle;
use Venusian\Surface\Tests\Bridge\Fakes\FakeSession;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;

it('registers program identity on the shuttle and hands it back', function () {
    $program = new ProgramShuttle((new FakeSession())->connect(), new FakeWindowDriver());
    $info = new AboutInfo(name: 'Hello Label', version: '0.8.0');

    expect($program->getAbout())->toBeNull()
        ->and($program->setAbout($info))->toBe($program)
        ->and($program->getAbout())->toBe($info);
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

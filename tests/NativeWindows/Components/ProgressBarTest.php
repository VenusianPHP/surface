<?php

use Surface\NativeWindows\Components\ProgressBar;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeProgressBar;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a progress bar at the component name path filling the root', function () {
    $window = new FakeWindow('main');

    $bar = new ProgressBar($window, 'load', 10, 20, 200, 8, progress: 0.4);

    expect($window->view('load'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('load.bar'))->toBeInstanceOf(FakeProgressBar::class)
        ->and($bar->part('bar')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 200, 'height' => 8])
        ->and($bar->progress())->toBe(0.4);
});

it('delegates progress reads and writes to the inner bar, clamp included', function () {
    $window = new FakeWindow('main');
    $bar = new ProgressBar($window, 'load', 0, 0, 200, 8, progress: 0.4);

    $bar->setProgress(0.75);

    expect($bar->progress())->toBe(0.75)
        ->and($bar->part('bar')->progress())->toBe(0.75);

    $bar->setProgress(1.5);

    expect($bar->progress())->toBe(1.0);
});

it('place stretches the inner bar to the new inner size', function () {
    $window = new FakeWindow('main');
    $bar = new ProgressBar($window, 'load', 0, 0, 200, 8);

    $bar->place(0, 0, 360, 12);

    expect($bar->part('bar')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 360, 'height' => 12]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $bar = new ProgressBar($window, 'load', 0, 0, 200, 8);

    $bar->remove();

    expect($window->view('load'))->toBeNull()
        ->and($window->view('load.bar'))->toBeNull()
        ->and($bar->part('bar'))->toBeNull();
});

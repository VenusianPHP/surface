<?php

use Surface\NativeWindows\Components\ProgressSpinner;
use Venusian\Surface\Tests\Support\Fakes\FakeGroup;
use Venusian\Surface\Tests\Support\Fakes\FakeSpinner;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('mounts a stopped spinner at the component name path filling the root', function () {
    $window = new FakeWindow('main');

    $spinner = new ProgressSpinner($window, 'busy', 10, 20, 24, 24);

    expect($window->view('busy'))->toBeInstanceOf(FakeGroup::class)
        ->and($window->view('busy.spinner'))->toBeInstanceOf(FakeSpinner::class)
        ->and($spinner->part('spinner')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 24, 'height' => 24])
        ->and($spinner->isSpinning())->toBeFalse();
});

it('delegates start and stop to the inner spinner', function () {
    $window = new FakeWindow('main');
    $spinner = new ProgressSpinner($window, 'busy', 0, 0, 24, 24);

    $spinner->start();

    expect($spinner->isSpinning())->toBeTrue()
        ->and($spinner->part('spinner')->isSpinning())->toBeTrue();

    $spinner->stop();

    expect($spinner->isSpinning())->toBeFalse()
        ->and($spinner->part('spinner')->isSpinning())->toBeFalse();
});

it('place stretches the inner spinner to the new inner size', function () {
    $window = new FakeWindow('main');
    $spinner = new ProgressSpinner($window, 'busy', 0, 0, 24, 24);

    $spinner->place(0, 0, 32, 32);

    expect($spinner->part('spinner')->frame())->toBe(['x' => 0, 'y' => 0, 'width' => 32, 'height' => 32]);
});

it('removal frees the root and part names', function () {
    $window = new FakeWindow('main');
    $spinner = new ProgressSpinner($window, 'busy', 0, 0, 24, 24);

    $spinner->remove();

    expect($window->view('busy'))->toBeNull()
        ->and($window->view('busy.spinner'))->toBeNull()
        ->and($spinner->part('spinner'))->toBeNull();
});

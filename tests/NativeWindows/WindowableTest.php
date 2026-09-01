<?php

use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/*
|--------------------------------------------------------------------------
| Windowable base behaviour
|--------------------------------------------------------------------------
|
| Windowable carries the two things every engine delegate shares: the name it
| was registered under, and the title() combo that reads or writes depending on
| whether it was handed an argument.
|
*/

it('carries the name it was constructed with', function () {
    expect((new FakeWindow('main'))->name())->toBe('main');
});

it('has no title until one is written', function () {
    expect((new FakeWindow('main'))->title())->toBeNull();
});

it('reads the title back when title is called with no argument', function () {
    $window = new FakeWindow('main');
    $window->setTitle('Demo Sketch');

    expect($window->title())->toBe('Demo Sketch');
});

it('writes the title and returns the window when title is called with one', function () {
    $window = new FakeWindow('main');

    expect($window->title('Demo Sketch'))->toBe($window)
        ->and($window->getTitle())->toBe('Demo Sketch');
});

it('returns the window from setTitle so calls can chain', function () {
    $window = new FakeWindow('main');

    expect($window->setTitle('First')->setTitle('Second'))->toBe($window)
        ->and($window->getTitle())->toBe('Second');
});

it('starts off screen and reports presentation state as it changes', function () {
    $window = new FakeWindow('main');

    expect($window->isPresenting())->toBeFalse();

    $window->present();
    expect($window->isPresenting())->toBeTrue();

    $window->destroy();
    expect($window->isPresenting())->toBeFalse();
});

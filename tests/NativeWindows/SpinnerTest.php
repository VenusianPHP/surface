<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Venusian\Surface\Tests\Support\Fakes\FakeSpinner;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/*
|--------------------------------------------------------------------------
| Conjuring a spinner
|--------------------------------------------------------------------------
|
| An indeterminate busy indicator. Surface holds the spinning flag it
| believes in; engines receive every transition through applySpinning().
| There is no determinate mode — OS-level occurrences carry no mid-flight
| progress, so Surface refuses to fake one.
|
*/

it('conjures a spinner, registers it by name and places it at once', function () {
    $window = new FakeWindow('main');

    $spinner = $window->spinner('busy', 10, 20, 32, 32);

    expect($spinner)->toBeInstanceOf(FakeSpinner::class)
        ->and($spinner->name())->toBe('busy')
        ->and($window->view('busy'))->toBe($spinner)
        ->and($spinner->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 32, 'height' => 32])
        ->and($spinner->applied_frames)->toBe([[10, 20, 32, 32]]);
});

it('refuses a second view under a taken spinner name', function () {
    $window = new FakeWindow('main');
    $window->spinner('busy', 0, 0, 32, 32);

    expect(fn () => $window->spinner('busy', 0, 0, 32, 32))
        ->toThrow(WindowableException::class, "View 'busy' already exists");
});

it('is not spinning when conjured', function () {
    $window = new FakeWindow('main');

    $spinner = $window->spinner('busy', 0, 0, 32, 32);

    expect($spinner->isSpinning())->toBeFalse()
        ->and($spinner->applied_spinnings)->toBe([]);
});

it('starts and the engine sees the transition', function () {
    $window = new FakeWindow('main');
    $spinner = $window->spinner('busy', 0, 0, 32, 32);

    $spinner->start();

    expect($spinner->isSpinning())->toBeTrue()
        ->and($spinner->applied_spinnings)->toBe([true]);
});

it('stops and the engine sees both transitions in order', function () {
    $window = new FakeWindow('main');
    $spinner = $window->spinner('busy', 0, 0, 32, 32);

    $spinner->start()->stop();

    expect($spinner->isSpinning())->toBeFalse()
        ->and($spinner->applied_spinnings)->toBe([true, false]);
});

it('hugs to the engine natural size like any view', function () {
    $window = new FakeWindow('main');
    $spinner = $window->spinner('busy', 10, 20, 1, 1);
    $spinner->natural_size = [24, 24];

    $spinner->hug();

    expect($spinner->frame())->toBe(['x' => 10, 'y' => 20, 'width' => 24, 'height' => 24]);
});

it('removal destroys the native node and frees the name', function () {
    $window = new FakeWindow('main');
    $spinner = $window->spinner('busy', 0, 0, 32, 32);

    $spinner->remove();

    expect($spinner->destroyed)->toBeTrue()
        ->and($window->view('busy'))->toBeNull();
});

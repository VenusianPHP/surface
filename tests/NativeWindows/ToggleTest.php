<?php

use Surface\Contracts\NativeWindows\Events\View\Toggled;
use Venusian\Surface\Tests\Support\Fakes\FakeToggle;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a toggle holding its state and places it at once', function () {
    $window = new FakeWindow('main');

    $toggle = $window->toggle('dark_mode', true, 10, 20, 40, 24);

    expect($toggle)->toBeInstanceOf(FakeToggle::class)
        ->and($toggle->isOn())->toBeTrue()
        ->and($toggle->applied_frames)->toBe([[10, 20, 40, 24]]);
});

it('setOn writes through only on change', function () {
    $window = new FakeWindow('main');
    $toggle = $window->toggle('dark_mode', false, 0, 0, 40, 24);

    $toggle->setOn(true);
    $toggle->setOn(true);
    $toggle->setOn(false);

    expect($toggle->isOn())->toBeFalse()
        ->and($toggle->applied_on)->toBe([true, false]);
});

it('an engine flip updates the state, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $toggle = $window->toggle('dark_mode', false, 0, 0, 40, 24)
        ->onToggle(function (bool $on) use (&$seen) { $seen[] = $on; });

    $toggle->flip(true);

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof Toggled);
    expect($toggle->isOn())->toBeTrue()
        ->and($seen)->toBe([true])
        ->and($mail->name)->toBe('main.dark_mode.toggled')
        ->and($mail->on)->toBeTrue();
});

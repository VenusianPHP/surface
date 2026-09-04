<?php

use Surface\Contracts\NativeWindows\Events\View\Toggled;
use Venusian\Surface\Tests\Support\Fakes\FakeCheckbox;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a checkbox with label and state, and places it at once', function () {
    $window = new FakeWindow('main');

    $box = $window->checkbox('remember', 'Remember me', true, 10, 20, 140, 24);

    expect($box)->toBeInstanceOf(FakeCheckbox::class)
        ->and($box->label())->toBe('Remember me')
        ->and($box->isChecked())->toBeTrue()
        ->and($box->applied_frames)->toBe([[10, 20, 140, 24]]);
});

it('setChecked writes through only on change', function () {
    $window = new FakeWindow('main');
    $box = $window->checkbox('remember', 'Remember me', false, 0, 0, 140, 24);

    $box->setChecked(true);
    $box->setChecked(true);

    expect($box->applied_checked)->toBe([true]);
});

it('an engine tick updates the state, invokes the hook, and rides the dock', function () {
    $dock = bareDock();
    $window = new FakeWindow('main');
    $window->setPool($dock);
    $seen = [];
    $box = $window->checkbox('remember', 'Remember me', false, 0, 0, 140, 24)
        ->onToggle(function (bool $checked) use (&$seen) { $seen[] = $checked; });

    $box->tick(true);

    $mail = $dock->drain()->first(fn (object $mail) => $mail instanceof Toggled);
    expect($box->isChecked())->toBeTrue()
        ->and($seen)->toBe([true])
        ->and($mail->name)->toBe('main.remember.toggled')
        ->and($mail->on)->toBeTrue();
});

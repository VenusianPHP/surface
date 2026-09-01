<?php

use Surface\Contracts\NativeWindows\Views\SpinnerHandle;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

it('conjures a spinner at rest', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $busy = $root->spinner('busy', 20, 20, 32, 32);

    expect($busy)->toBeInstanceOf(SpinnerHandle::class)
        ->and($busy->type())->toBe(ViewType::SPINNER)
        ->and($busy->isSpinning())->toBeFalse()
        ->and($log->ops())->toBe(['createSpinner', 'attach', 'setFrame']);
});

it('starts and stops natively and remembers it', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $busy = $root->spinner('busy', 20, 20, 32, 32);
    $log->clear();

    $busy->start();
    expect($busy->isSpinning())->toBeTrue();

    $busy->stop();
    expect($busy->isSpinning())->toBeFalse()
        ->and($log->ops())->toBe(['start', 'stop']);
});

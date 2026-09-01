<?php

use Surface\Contracts\NativeWindows\Views\SwitchHandle;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

it('conjures a switch with its starting state', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $dark = $root->switch('dark', 20, 20, 60, 28, true);

    expect($dark)->toBeInstanceOf(SwitchHandle::class)
        ->and($dark->type())->toBe(ViewType::SWITCH)
        ->and($log->ops())->toBe(['createSwitch', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe([true])
        ->and($dark->isOn())->toBeTrue();
});

it('flips natively and pulls state from native', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $dark = $root->switch('dark', 20, 20, 60, 28);
    $log->clear();

    $dark->on()->on(false);

    expect($log->ops())->toBe(['setOn', 'setOn'])
        ->and($log->entries[1]['args'])->toBe([false])
        ->and($dark->isOn())->toBeFalse();
});

it('routes toggle through a trampoline', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $dark = $root->switch('dark', 20, 20, 60, 28);
    $log->clear();
    $received = null;

    $dark->onToggle(function (SwitchHandle $s) use (&$received): void { $received = $s; });
    ($log->entries[0]['args'][0])();

    expect($received)->toBe($dark);
});

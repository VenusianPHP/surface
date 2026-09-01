<?php

use Surface\Contracts\NativeWindows\Views\ProgressHandle;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

it('conjures a progress bar with a clamped starting fraction', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $meter = $root->progress('meter', 20, 20, 300, 20, 1.5);

    expect($meter)->toBeInstanceOf(ProgressHandle::class)
        ->and($meter->type())->toBe(ViewType::PROGRESS)
        ->and($log->ops())->toBe(['createProgress', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe([1.0]);
});

it('pushes fractions clamped to 0..1', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $meter = $root->progress('meter', 20, 20, 300, 20);
    $log->clear();

    $meter->fraction(0.42)->fraction(-3.0)->fraction(7.0);

    expect(array_column($log->entries, 'args'))->toBe([[0.42], [0.0], [1.0]]);
});

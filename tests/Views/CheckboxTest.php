<?php

use Surface\Contracts\NativeWindows\Views\CheckboxHandle;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeCheckbox;

function checkboxUnderTest(CallLog $log, bool $checked = false): FakeCheckbox
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $box = $root->checkbox('agree', 'Agree', 20, 20, 200, 24, $checked);
    $log->clear();

    return $box;
}

it('conjures a checkbox with title and starting state', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $box = $root->checkbox('agree', 'Agree', 20, 20, 200, 24, true);

    expect($box)->toBeInstanceOf(CheckboxHandle::class)
        ->and($box->type())->toBe(ViewType::CHECKBOX)
        ->and($log->ops())->toBe(['createCheckbox', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe(['Agree', true])
        ->and($box->isChecked())->toBeTrue();
});

it('checks and unchecks natively', function () {
    $log = new CallLog;
    $box = checkboxUnderTest($log);

    $box->check();
    $box->check(false);

    expect($log->ops())->toBe(['setChecked', 'setChecked'])
        ->and($log->entries[0]['args'])->toBe([true])
        ->and($log->entries[1]['args'])->toBe([false])
        ->and($box->isChecked())->toBeFalse();
});

it('pulls checked state from native', function () {
    $log = new CallLog;
    $box = checkboxUnderTest($log);
    $box->checked = true;

    expect($box->isChecked())->toBeTrue()
        ->and($log->ops())->toBe(['isChecked']);
});

it('pushes title and routes toggle through a trampoline', function () {
    $log = new CallLog;
    $box = checkboxUnderTest($log);
    $received = null;

    $box->title('I agree')->onToggle(function (CheckboxHandle $c) use (&$received): void { $received = $c; });
    ($log->entries[1]['args'][0])();

    expect($log->ops())->toBe(['setTitle', 'onToggle'])
        ->and($received)->toBe($box);
});

<?php

use Surface\Contracts\NativeWindows\Views\RadioHandle;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

it('conjures a radio in a group', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $small = $root->radio('small', 'Small', 'size', 20, 20, 100, 24);

    expect($small)->toBeInstanceOf(RadioHandle::class)
        ->and($small->type())->toBe(ViewType::RADIO)
        ->and($small->group())->toBe('size')
        ->and($log->ops())->toBe(['createRadio', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe(['Small', 'size']);
});

it('selects natively and pulls selection from native', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $small = $root->radio('small', 'Small', 'size', 20, 20, 100, 24);
    $log->clear();

    $small->select();
    $small->selected = true;

    expect($log->entries[0])->toBe(['op' => 'setSelected', 'pointer' => $small->pointer(), 'args' => [true]])
        ->and($small->isSelected())->toBeTrue()
        ->and($log->ops())->toBe(['setSelected', 'isSelected']);
});

it('pushes title and routes select through a trampoline', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $large = $root->radio('large', 'Large', 'size', 20, 50, 100, 24);
    $log->clear();
    $received = null;

    $large->title('Big')->onSelect(function (RadioHandle $r) use (&$received): void { $received = $r; });
    ($log->entries[1]['args'][0])();

    expect($log->ops())->toBe(['setTitle', 'onSelect'])
        ->and($received)->toBe($large);
});

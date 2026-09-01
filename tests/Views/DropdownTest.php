<?php

use Surface\Contracts\NativeWindows\Views\DropdownHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeDropdown;

function dropdownUnderTest(CallLog $log): FakeDropdown
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $dropdown = $root->dropdown('flavor', ['Vanilla', 'Chocolate', 'Mint'], 20, 20, 200, 28, 1);
    $log->clear();

    return $dropdown;
}

it('conjures a dropdown with items and a selection', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $dropdown = $root->dropdown('flavor', ['Vanilla', 'Chocolate', 'Mint'], 20, 20, 200, 28, 1);

    expect($dropdown)->toBeInstanceOf(DropdownHandle::class)
        ->and($dropdown->type())->toBe(ViewType::DROPDOWN)
        ->and($dropdown->items())->toBe(['Vanilla', 'Chocolate', 'Mint'])
        ->and($log->ops())->toBe(['createDropdown', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe([['Vanilla', 'Chocolate', 'Mint'], 1]);
});

it('refuses an empty item list or an out-of-range selection at birth', function (array $items, int $selected) {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));

    $root->dropdown('flavor', $items, 20, 20, 200, 28, $selected);
})->with([
    'empty' => [[], 0],
    'too high' => [['A', 'B'], 2],
    'negative' => [['A', 'B'], -1],
])->throws(ViewException::class);

it('replaces items as a plain list', function () {
    $log = new CallLog;
    $dropdown = dropdownUnderTest($log);

    $dropdown->setItems(['x' => 'Small', 'y' => 'Large']);

    expect($dropdown->items())->toBe(['Small', 'Large'])
        ->and($log->entries[0])->toBe(['op' => 'setItems', 'pointer' => $dropdown->pointer(), 'args' => [['Small', 'Large']]]);
});

it('refuses to replace the items with an empty list', function () {
    dropdownUnderTest(new CallLog)->setItems([]);
})->throws(ViewException::class);

it('selects by index, pulls the selection and names the item', function () {
    $log = new CallLog;
    $dropdown = dropdownUnderTest($log);

    $dropdown->select(2);

    expect($log->entries[0]['args'])->toBe([2])
        ->and($dropdown->selected())->toBe(2)
        ->and($dropdown->selectedItem())->toBe('Mint');
});

it('refuses to select outside the list', function () {
    dropdownUnderTest(new CallLog)->select(3);
})->throws(ViewException::class, '3');

it('routes change through a trampoline', function () {
    $log = new CallLog;
    $dropdown = dropdownUnderTest($log);
    $received = null;

    $dropdown->onChange(function (DropdownHandle $d) use (&$received): void { $received = $d; });
    ($log->entries[0]['args'][0])();

    expect($received)->toBe($dropdown);
});

it('clears the change callback by handing native null', function () {
    $log = new CallLog;
    $dropdown = dropdownUnderTest($log);

    $dropdown->onChange(null);

    expect($log->entries[0])->toBe(['op' => 'onChange', 'pointer' => $dropdown->pointer(), 'args' => [null]]);
});

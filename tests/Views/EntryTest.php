<?php

use Surface\Contracts\NativeWindows\Views\EntryHandle;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeEntry;

function entryUnderTest(CallLog $log): FakeEntry
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $entry = $root->entry('name', 'Ada', 20, 20, 300, 28);
    $log->clear();

    return $entry;
}

it('conjures an entry with its starting text', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $entry = $root->entry('name', 'Ada', 20, 20, 300, 28);

    expect($entry)->toBeInstanceOf(EntryHandle::class)
        ->and($entry->type())->toBe(ViewType::ENTRY)
        ->and($log->ops())->toBe(['createEntry', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe(['Ada']);
});

it('conjures a password as an entry of type PASSWORD', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $secret = $root->password('secret', '', 20, 60, 300, 28);

    expect($secret)->toBeInstanceOf(EntryHandle::class)
        ->and($secret->type())->toBe(ViewType::PASSWORD)
        ->and($log->ops())->toBe(['createPassword', 'attach', 'setFrame']);
});

it('pulls its value from native every time', function () {
    $log = new CallLog;
    $entry = entryUnderTest($log);
    $entry->stored = 'typed by the user';

    expect($entry->value())->toBe('typed by the user')
        ->and($log->ops())->toBe(['getValue']);
});

it('pushes a value and placeholder', function () {
    $log = new CallLog;
    $entry = entryUnderTest($log);

    $entry->setValue('Grace')->placeholder('Your name');

    expect($log->ops())->toBe(['setValue', 'setPlaceholder'])
        ->and($log->entries[0]['args'])->toBe(['Grace'])
        ->and($log->entries[1]['args'])->toBe(['Your name'])
        ->and($entry->value())->toBe('Grace');
});

it('pushes text colour as channels', function () {
    $log = new CallLog;
    $entry = entryUnderTest($log);

    $entry->textColor(Color::rgb(255, 0, 0));

    expect($log->entries[0]['op'])->toBe('setTextColor')
        ->and($log->entries[0]['args'])->toBe([1.0, 0.0, 0.0, 1.0]);
});

it('routes change and submit callbacks through trampolines', function () {
    $log = new CallLog;
    $entry = entryUnderTest($log);
    $seen = [];

    $entry->onChange(function (EntryHandle $e) use (&$seen): void { $seen[] = 'change'; })
        ->onSubmit(function (EntryHandle $e) use (&$seen): void { $seen[] = 'submit'; });

    expect($log->ops())->toBe(['onChange', 'onSubmit']);
    ($log->entries[0]['args'][0])();
    ($log->entries[1]['args'][0])();

    expect($seen)->toBe(['change', 'submit']);
});

<?php

use Surface\NativeWindows\Enums\FontWeight;
use Surface\NativeWindows\Enums\TextAlignment;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeLabel;

function labelUnderTest(CallLog $log): FakeLabel
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $label = $root->label('title', 'Hello', 20, 20, 320, 32);
    $log->clear();

    return $label;
}

it('pushes text to native and stays fluent', function () {
    $log = new CallLog;
    $label = labelUnderTest($log);

    $result = $label->text('Changed');

    expect($result)->toBe($label)
        ->and($log->entries)->toBe([['op' => 'setText', 'pointer' => $label->pointer(), 'args' => ['Changed']]]);
});

it('pushes text colour as 0-1 channels', function () {
    $log = new CallLog;
    $label = labelUnderTest($log);

    $label->textColor(Color::rgb(255, 0, 0, 0.5));

    expect($log->entries[0]['op'])->toBe('setTextColor')
        ->and($log->entries[0]['args'])->toBe([1.0, 0.0, 0.0, 0.5]);
});

it('pushes font family, size and weight', function () {
    $log = new CallLog;
    $label = labelUnderTest($log);

    $label->font('Menlo', 14.5, FontWeight::BOLD);

    expect($log->entries[0]['op'])->toBe('setFont')
        ->and($log->entries[0]['args'])->toBe(['Menlo', 14.5, FontWeight::BOLD]);
});

it('defaults font weight to regular', function () {
    $log = new CallLog;
    $label = labelUnderTest($log);

    $label->font('', 24.0);

    expect($log->entries[0]['args'])->toBe(['', 24.0, FontWeight::REGULAR]);
});

it('moves in realtime and remembers the new frame', function () {
    $log = new CallLog;
    $label = labelUnderTest($log);

    $label->position(100, 200);

    expect($label->frame())->toEqual(new Frame(100, 200, 320, 32))
        ->and($log->entries)->toBe([['op' => 'setFrame', 'pointer' => $label->pointer(), 'args' => [100, 200, 320, 32]]]);
});

it('resizes in realtime and remembers the new frame', function () {
    $log = new CallLog;
    $label = labelUnderTest($log);

    $label->size(50, 10);

    expect($label->frame())->toEqual(new Frame(20, 20, 50, 10))
        ->and($log->entries)->toBe([['op' => 'setFrame', 'pointer' => $label->pointer(), 'args' => [20, 20, 50, 10]]]);
});

it('pushes background colour', function () {
    $log = new CallLog;
    $label = labelUnderTest($log);

    $label->bgColor(Color::hex('#000000'));

    expect($log->entries[0]['op'])->toBe('setBgColor')
        ->and($log->entries[0]['args'])->toBe([0.0, 0.0, 0.0, 1.0]);
});

it('pushes horizontal alignment', function () {
    $log = new CallLog;
    $label = labelUnderTest($log);

    $result = $label->align(TextAlignment::CENTER);

    expect($result)->toBe($label)
        ->and($log->entries)->toBe([['op' => 'setAlignment', 'pointer' => $label->pointer(), 'args' => [TextAlignment::CENTER]]]);
});

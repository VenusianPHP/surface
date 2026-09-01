<?php

use Surface\Contracts\NativeWindows\Views\TextHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeText;

function textUnderTest(CallLog $log): FakeText
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $text = $root->text('body', 'Hello', 20, 20, 300, 200);
    $log->clear();

    return $text;
}

it('conjures a text area through create, attach, frame', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $text = $root->text('body', 'Hello', 20, 20, 300, 200, false);

    expect($text)->toBeInstanceOf(TextHandle::class)
        ->and($text->type())->toBe(ViewType::TEXT)
        ->and($text->isEditable())->toBeFalse()
        ->and($root->child('body'))->toBe($text)
        ->and($log->ops())->toBe(['createText', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe(['Hello', false]);
});

it('pushes and pulls its value without firing onChange', function () {
    $log = new CallLog;
    $text = textUnderTest($log);
    $fired = 0;
    $text->onChange(function () use (&$fired): void { $fired++; });
    $log->clear();

    $text->setValue('rewritten');

    expect($text->value())->toBe('rewritten')
        ->and($fired)->toBe(0)
        ->and($log->ops())->toBe(['setValue', 'getValue']);
});

it('toggles editable as bookkeeping', function () {
    $log = new CallLog;
    $text = textUnderTest($log);

    $text->editable(false);

    expect($text->isEditable())->toBeFalse()
        ->and($log->entries[0])->toBe(['op' => 'setEditable', 'pointer' => $text->pointer(), 'args' => [false]]);
});

it('pushes text colour and routes change through a trampoline', function () {
    $log = new CallLog;
    $text = textUnderTest($log);
    $received = null;

    $text->textColor(Color::rgb(255, 0, 0))
        ->onChange(function (TextHandle $t) use (&$received): void { $received = $t; });

    $trampoline = $log->of('onChange')[0]['args'][0];
    $trampoline();

    expect($log->of('setTextColor')[0]['args'])->toBe([1.0, 0.0, 0.0, 1.0])
        ->and($received)->toBe($text);
});

it('refuses mutation after removal', function () {
    $text = textUnderTest(new CallLog);
    $text->remove();

    expect(fn () => $text->setValue('x'))->toThrow(ViewException::class);
});

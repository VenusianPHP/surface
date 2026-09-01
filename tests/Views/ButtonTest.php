<?php

use Surface\Contracts\NativeWindows\Views\ButtonHandle;
use Surface\NativeWindows\Enums\FontWeight;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeButton;

function buttonUnderTest(CallLog $log): FakeButton
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $button = $root->button('go', 'Go', 20, 20, 120, 32);
    $log->clear();

    return $button;
}

it('conjures a button through create, attach, frame', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $button = $root->button('go', 'Go', 20, 20, 120, 32);

    expect($button)->toBeInstanceOf(ButtonHandle::class)
        ->and($button->type())->toBe(ViewType::BUTTON)
        ->and($root->child('go'))->toBe($button)
        ->and($button->frame())->toEqual(new Frame(20, 20, 120, 32))
        ->and($log->ops())->toBe(['createButton', 'attach', 'setFrame'])
        ->and($log->entries[0]['args'])->toBe(['Go']);
});

it('pushes a new title', function () {
    $log = new CallLog;
    $button = buttonUnderTest($log);

    expect($button->title('Run'))->toBe($button)
        ->and($log->entries)->toBe([['op' => 'setTitle', 'pointer' => $button->pointer(), 'args' => ['Run']]]);
});

it('toggles enabled natively and remembers it', function () {
    $log = new CallLog;
    $button = buttonUnderTest($log);

    $button->enabled(false);

    expect($button->isEnabled())->toBeFalse()
        ->and($log->entries[0])->toBe(['op' => 'setEnabled', 'pointer' => $button->pointer(), 'args' => [false]]);

    $button->enabled();

    expect($button->isEnabled())->toBeTrue()
        ->and($log->entries[1]['args'])->toBe([true]);
});

it('pushes a font', function () {
    $log = new CallLog;
    $button = buttonUnderTest($log);

    $button->font('', 14.0, FontWeight::BOLD);

    expect($log->entries[0])->toBe(['op' => 'setFont', 'pointer' => $button->pointer(), 'args' => ['', 14.0, FontWeight::BOLD]]);
});

it('hands native an argless trampoline that calls back with the handle', function () {
    $log = new CallLog;
    $button = buttonUnderTest($log);
    $received = null;

    $button->onClick(function (ButtonHandle $clicked) use (&$received): void {
        $received = $clicked;
    });

    $trampoline = $log->entries[0]['args'][0];

    expect($log->entries[0]['op'])->toBe('onClick')
        ->and($trampoline)->toBeInstanceOf(Closure::class);

    $trampoline();

    expect($received)->toBe($button);
});

it('clears a callback by handing native null', function () {
    $log = new CallLog;
    $button = buttonUnderTest($log);

    $button->onClick(null);

    expect($log->entries[0])->toBe(['op' => 'onClick', 'pointer' => $button->pointer(), 'args' => [null]]);
});

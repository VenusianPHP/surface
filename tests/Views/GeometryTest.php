<?php

use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Size;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeLabel;

function measuredLabel(CallLog $log, int $width = 88, int $height = 29): FakeLabel
{
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $label = $root->label('title', 'Hello', 20, 20, 320, 32);
    $label->natural = new Size($width, $height);
    $log->clear();

    return $label;
}

it('measures its natural size through the driver without resizing', function () {
    $log = new CallLog;
    $label = measuredLabel($log);

    $size = $label->measure();

    expect($size)->toEqual(new Size(88, 29))
        ->and($label->frame())->toEqual(new Frame(20, 20, 320, 32))
        ->and($log->ops())->toBe(['measure']);
});

it('hugs its natural size in place', function () {
    $log = new CallLog;
    $label = measuredLabel($log);

    $result = $label->hug();

    expect($result)->toBe($label)
        ->and($label->frame())->toEqual(new Frame(20, 20, 88, 29))
        ->and($log->ops())->toBe(['measure', 'setFrame'])
        ->and($log->entries[1]['args'])->toBe([20, 20, 88, 29]);
});

it('centers its current frame inside its parent', function () {
    $log = new CallLog;
    $label = measuredLabel($log);
    $label->size(88, 29);
    $log->clear();

    $result = $label->center();

    expect($result)->toBe($label)
        ->and($label->frame())->toEqual(new Frame(276, 225, 88, 29))
        ->and($log->entries)->toBe([['op' => 'setFrame', 'pointer' => $label->pointer(), 'args' => [276, 225, 88, 29]]]);
});

it('hugs then centers fluently', function () {
    $label = measuredLabel(new CallLog);

    $label->hug()->center();

    expect($label->frame())->toEqual(new Frame(276, 225, 88, 29));
});

it('centers inside a nested box, not the window', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $side = $root->box('side', 40, 40, 200, 100);
    $label = $side->label('title', 'Hi', 0, 0, 50, 10);

    $label->center();

    expect($label->frame())->toEqual(new Frame(75, 45, 50, 10));
});

it('reports a box natural size as its frame with no native call', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $side = $root->box('side', 40, 40, 200, 100);
    $log->clear();

    expect($root->measure())->toEqual(new Size(640, 480))
        ->and($side->measure())->toEqual(new Size(200, 100))
        ->and($log->entries)->toBe([]);
});

it('refuses to hug or center the root', function (Closure $mutate) {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));

    $mutate($root);
})->with([
    'hug' => fn ($root) => $root->hug(),
    'center' => fn ($root) => $root->center(),
])->throws(ViewException::class, 'Root');

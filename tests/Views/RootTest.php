<?php

use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Views\Color;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

function rootUnderTest(?CallLog $log = null): FakeBox
{
    return new FakeBox($log ?? new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
}

it('is the root with an empty path', function () {
    $root = rootUnderTest();
    $child = $root->box('side', 0, 0, 10, 10);

    expect($root->isRoot())->toBeTrue()
        ->and($root->path())->toBe('')
        ->and($root->parent())->toBeNull()
        ->and($child->isRoot())->toBeFalse();
});

it('refuses to be moved, resized or removed', function (Closure $mutate) {
    $log = new CallLog;
    $root = rootUnderTest($log);

    try {
        $mutate($root);
    } finally {
        expect($log->entries)->toBe([]);
    }
})->with([
    'position' => fn ($root) => $root->position(1, 1),
    'size' => fn ($root) => $root->size(1, 1),
    'remove' => fn ($root) => $root->remove(),
])->throws(ViewException::class, 'Root');

it('accepts a background colour', function () {
    $log = new CallLog;
    $root = rootUnderTest($log);

    $root->bgColor(Color::hex('#101418'));

    expect($log->ops())->toBe(['setBgColor']);
});

it('tracks the window content size without touching native', function () {
    $log = new CallLog;
    $root = rootUnderTest($log);

    $root->resize(800, 600);

    expect($root->frame())->toEqual(new Frame(0, 0, 800, 600))
        ->and($log->entries)->toBe([]);
});

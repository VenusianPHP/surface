<?php

use Surface\Contracts\NativeWindows\Views\ScrollHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Point;
use Surface\NativeWindows\Views\Size;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;

it('conjures a scroll whose children attach to the content pointer', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $scroll = $root->scroll('list', 400, 1200, 10, 10, 360, 400);
    $item = $scroll->label('row', '0', 0, 0, 100, 20);

    expect($scroll)->toBeInstanceOf(ScrollHandle::class)
        ->and($scroll->type())->toBe(ViewType::SCROLL)
        ->and($scroll->contentSize())->toEqual(new Size(400, 1200))
        ->and($scroll->attachPointer())->not->toBe($scroll->pointer())
        ->and($item->path())->toBe('list.row')
        ->and($log->of('attach')[1]['pointer'])->toBe($scroll->attachPointer());
});

it('refuses a non-positive content size', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));

    $root->scroll('list', 0, 100, 0, 0, 10, 10);
})->throws(ViewException::class);

it('pushes content size as bookkeeping and pulls offset', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $scroll = $root->scroll('list', 400, 1200, 0, 0, 200, 200);
    $log->clear();

    $scroll->setContentSize(400, 2000)->scrollTo(0, 1200);

    expect($scroll->contentSize())->toEqual(new Size(400, 2000))
        ->and($scroll->offset())->toEqual(new Point(0, 1200))
        ->and($log->ops())->toBe(['setContentSize', 'scrollTo', 'offset']);
});

it('centres children on the scrollable content, not the viewport', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $scroll = $root->scroll('list', 400, 1200, 0, 0, 200, 200);
    $hero = $scroll->label('hero', 'x', 0, 0, 100, 20);

    $hero->center();

    expect($hero->frame())->toEqual(new Frame(150, 590, 100, 20));
});

it('names the bad size when content is not positive', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $scroll = $root->scroll('list', 400, 1200, 0, 0, 200, 200);

    expect(fn () => $root->scroll('other', 0, 100, 0, 0, 10, 10))->toThrow(ViewException::class, '0x100')
        ->and(fn () => $scroll->setContentSize(400, 0))->toThrow(ViewException::class, '400x0');
});

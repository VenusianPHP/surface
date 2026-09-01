<?php

use Surface\Contracts\NativeWindows\Views\ParentHandle;
use Surface\Contracts\NativeWindows\Views\SplitHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\Orientation;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Surface\NativeWindows\Views\Size;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeWindow;

it('conjures a split with two native-owned panes named first and second', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 900, 600));

    $split = $root->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);

    expect($split)->toBeInstanceOf(SplitHandle::class)
        ->and($split)->toBeInstanceOf(ParentHandle::class)
        ->and($split->type())->toBe(ViewType::SPLIT)
        ->and($split->orientation())->toBe(Orientation::HORIZONTAL)
        ->and($split->first()->nickname())->toBe('first')
        ->and($split->second()->nickname())->toBe('second')
        ->and($split->first()->frame())->toEqual(new Frame(0, 0, 0, 0))
        ->and($root->find('main.first'))->toBe($split->first())
        ->and($log->of('setDivider')[0]['args'])->toBe([360]);
});

it('defaults a missing divider to half the axis', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 900, 600));

    $root->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560);

    expect($log->of('setDivider')[0]['args'])->toBe([450]);
});

it('refuses to position, size, hug, center or remove a pane', function () {
    $split = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 900, 600)))
        ->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);
    $pane = $split->first();

    expect(fn () => $pane->position(1, 1))->toThrow(ViewException::class, 'move')
        ->and(fn () => $pane->size(1, 1))->toThrow(ViewException::class, 'resize')
        ->and(fn () => $pane->hug())->toThrow(ViewException::class, 'hug')
        ->and(fn () => $pane->center())->toThrow(ViewException::class, 'center')
        ->and(fn () => $pane->remove())->toThrow(ViewException::class, 'be removed')
        ->and(fn () => $split->removeChild('first'))->toThrow(ViewException::class, 'be removed');
});

it('pulls pane sizes from native on every read, never caching', function () {
    $log = new CallLog;
    $split = (new FakeBox($log, 1, 'root', null, new Frame(0, 0, 900, 600)))
        ->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);
    $pane = $split->first();
    $log->clear();

    $pane->nativeSize = new Size(360, 560);
    $first = $pane->frame();
    $pane->nativeSize = new Size(372, 560);

    expect($first)->toEqual(new Frame(0, 0, 360, 560))
        ->and($pane->frame())->toEqual(new Frame(0, 0, 372, 560))
        ->and($pane->measure())->toEqual(new Size(372, 560))
        ->and($log->ops())->toBe(['currentSize', 'currentSize', 'currentSize']);
});

it('conjures a split without reading pane sizes', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 900, 600));
    $log->clear();

    $root->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);

    expect($log->of('currentSize'))->toBe([]);
});

it('hands the split to an onDrag callback that sees fresh pane sizes', function () {
    $log = new CallLog;
    $split = (new FakeBox($log, 1, 'root', null, new Frame(0, 0, 900, 600)))
        ->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);
    $seen = null;
    $split->onDrag(function (SplitHandle $s) use (&$seen): void { $seen = $s->first()->frame(); });
    $trampoline = $log->of('onDrag')[0]['args'][0];
    $split->first()->nativeSize = new Size(372, 560);

    $trampoline();

    expect($seen)->toEqual(new Frame(0, 0, 372, 560));
});

it('centres a child on the pane size native reports right now', function () {
    $split = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 900, 600)))
        ->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);
    $title = $split->first()->label('title', 'Hi', 0, 0, 80, 20);
    $split->first()->nativeSize = new Size(360, 560);

    $title->center();

    expect($title->frame())->toEqual(new Frame(140, 270, 80, 20));
});

it('stops asking native once a pane is dead', function () {
    $log = new CallLog;
    $window = new FakeWindow($log, 900, 600);
    $split = $window->root()->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);
    $split->first()->nativeSize = new Size(360, 560);
    $split->first()->frame();
    $window->close();
    $log->clear();

    expect($split->first()->frame())->toEqual(new Frame(0, 0, 360, 560))
        ->and($log->ops())->toBe([]);
});

it('does not read pane sizes on pollResize', function () {
    $log = new CallLog;
    $window = new FakeWindow($log, 900, 600);
    $window->root()->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);
    $log->clear();

    $window->pollResize();

    expect($log->of('currentSize'))->toBe([]);
});

it('lets a pane conjure children found through the split', function () {
    $split = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 900, 600)))
        ->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);
    $title = $split->first()->label('title', 'Hi', 0, 0, 80, 20);

    expect($split->find('first.title'))->toBe($title)
        ->and($title->path())->toBe('main.first.title');
});

it('kills both panes and their children when the window closes', function () {
    $window = new FakeWindow(new CallLog, 900, 600);
    $split = $window->root()->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);
    $title = $split->first()->label('title', 'Hi', 0, 0, 80, 20);

    $window->close();

    expect($split->isAlive())->toBeFalse()
        ->and($split->first()->isAlive())->toBeFalse()
        ->and($split->second()->isAlive())->toBeFalse()
        ->and($title->isAlive())->toBeFalse()
        ->and(fn () => $split->first()->label('late', 'x', 0, 0, 1, 1))->toThrow(ViewException::class, 'main.first');
});

it('refuses pane removal through the window path too', function () {
    $window = new FakeWindow(new CallLog, 900, 600);
    $window->root()->split('main', Orientation::HORIZONTAL, 0, 0, 900, 560, 360);

    $window->removeFromTree('main.first');
})->throws(ViewException::class, 'main.first');

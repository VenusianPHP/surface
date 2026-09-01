<?php

use Surface\Contracts\NativeWindows\Views\PopoverHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeWindow;

it('registers a popover detached, never attached or framed', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));

    $tip = $root->popover('tip', 260, 120);

    expect($tip)->toBeInstanceOf(PopoverHandle::class)
        ->and($tip->type())->toBe(ViewType::POPOVER)
        ->and($tip->frame())->toEqual(new Frame(0, 0, 260, 120))
        ->and($root->child('tip'))->toBe($tip)
        ->and($log->ops())->toBe(['createPopover'])
        ->and($tip->attachPointer())->not->toBe($tip->pointer());
});

it('shows against a live same-window anchor and can move', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $button = $root->button('info', 'Info', 10, 10, 80, 24);
    $other = $root->button('ok', 'OK', 10, 40, 80, 24);
    $tip = $root->popover('tip', 260, 120);
    $log->clear();

    $tip->show($button)->show($other);

    expect($tip->isShown())->toBeTrue()
        ->and($log->of('show')[0]['args'])->toBe([$button->pointer()])
        ->and($log->of('show')[1]['args'])->toBe([$other->pointer()]);
});

it('rejects a dead or foreign anchor', function () {
    $home = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $away = new FakeBox(new CallLog, 9, 'root', null, new Frame(0, 0, 640, 480));
    $local = $home->button('info', 'Info', 0, 0, 80, 24);
    $foreign = $away->button('x', 'X', 0, 0, 80, 24);
    $tip = $home->popover('tip', 260, 120);

    expect(fn () => $tip->show($foreign))->toThrow(ViewException::class, 'tip');

    $local->remove();

    expect(fn () => $tip->show($local))->toThrow(ViewException::class, 'tip');
});

it('fires onClose when hide() is called, never when remove() is', function () {
    $root = new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480));
    $tip = $root->popover('tip', 260, 120);
    $closed = 0;
    $tip->onClose(function () use (&$closed): void { $closed++; });

    $tip->hide();

    expect($closed)->toBe(1);

    $tip->remove();

    expect($closed)->toBe(1)
        ->and($tip->isAlive())->toBeFalse();
});

it('sets content size and refuses to be positioned', function () {
    $tip = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480)))->popover('tip', 260, 120);

    $tip->size(300, 180);

    expect($tip->frame())->toEqual(new Frame(0, 0, 300, 180))
        ->and(fn () => $tip->position(10, 10))->toThrow(ViewException::class, 'move');
});

it('lets children sit on the content pointer', function () {
    $log = new CallLog;
    $tip = (new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480)))->popover('tip', 260, 120);
    $log->clear();

    $ok = $tip->button('ok', 'OK', 10, 80, 80, 24);

    expect($ok->path())->toBe('tip.ok')
        ->and($log->of('attach')[0]['pointer'])->toBe($tip->attachPointer());
});

it('dies with the window', function () {
    $window = new FakeWindow(new CallLog);
    $tip = $window->root()->popover('tip', 260, 120);

    $window->close();

    expect($tip->isAlive())->toBeFalse();
});

it('hugs by pushing content size, never a frame', function () {
    $log = new CallLog;
    $tip = (new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480)))->popover('tip', 260, 120);
    $log->clear();

    $tip->hug();

    expect($log->ops())->toBe(['setContentSize'])
        ->and($tip->frame())->toEqual(new Frame(0, 0, 260, 120));
});

it('destroys its native popover when an ancestor is removed', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $panel = $root->box('panel', 0, 0, 100, 100);
    $tip = $panel->popover('tip', 260, 120);
    $log->clear();

    $panel->remove();

    expect($log->ops())->toBe(['detach', 'detach'])
        ->and($log->of('detach')[0]['pointer'])->toBe($panel->pointer())
        ->and($log->of('detach')[1]['pointer'])->toBe($tip->pointer())
        ->and($tip->isAlive())->toBeFalse();
});

it('destroys its native popover before the window goes', function () {
    $log = new CallLog;
    $window = new FakeWindow($log);
    $tip = $window->root()->popover('tip', 260, 120);
    $log->clear();

    $window->close();

    expect($log->ops())->toBe(['detach', 'closeWindow'])
        ->and($log->of('detach')[0]['pointer'])->toBe($tip->pointer());
});

it('destroys natively exactly once when removed directly', function () {
    $log = new CallLog;
    $tip = (new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480)))->popover('tip', 260, 120);
    $log->clear();

    $tip->remove();

    expect($log->ops())->toBe(['detach']);
});

it('refuses itself or its own descendants as an anchor', function () {
    $tip = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480)))->popover('tip', 260, 120);
    $ok = $tip->button('ok', 'OK', 0, 0, 40, 20);

    expect(fn () => $tip->show($tip))->toThrow(ViewException::class, 'tip')
        ->and(fn () => $tip->show($ok))->toThrow(ViewException::class, 'tip');
});

it('throws when native cannot show against an off-screen anchor', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $button = $root->button('info', 'Info', 10, 10, 80, 24);
    $tip = $root->popover('tip', 260, 120);
    $tip->canShow = false;

    expect(fn () => $tip->show($button))->toThrow(ViewException::class, 'on screen')
        ->and($tip->isShown())->toBeFalse();
});

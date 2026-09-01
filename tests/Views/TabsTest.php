<?php

use Surface\Contracts\NativeWindows\Views\TabsHandle;
use Surface\Contracts\NativeWindows\Views\ViewException;
use Surface\NativeWindows\Enums\ViewType;
use Surface\NativeWindows\Views\Frame;
use Venusian\Surface\Tests\Views\Fakes\CallLog;
use Venusian\Surface\Tests\Views\Fakes\FakeBox;
use Venusian\Surface\Tests\Views\Fakes\FakeWindow;

it('conjures tabs and pages in insertion order', function () {
    $log = new CallLog;
    $root = new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480));
    $tabs = $root->tabs('nav', 0, 0, 360, 560);
    $notes = $tabs->page('notes', 'Notes');
    $picture = $tabs->page('picture', 'Picture');

    expect($tabs)->toBeInstanceOf(TabsHandle::class)
        ->and($tabs->type())->toBe(ViewType::TABS)
        ->and($tabs->count())->toBe(2)
        ->and(array_keys($tabs->pages()))->toBe(['notes', 'picture'])
        ->and($tabs->selected())->toBe('notes')
        ->and($root->find('nav.notes'))->toBe($notes)
        ->and($root->find('nav.picture'))->toBe($picture)
        ->and($notes->nickname())->toBe('notes');
});

it('reports null when no page is selected', function () {
    $tabs = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480)))
        ->tabs('nav', 0, 0, 360, 560);

    expect($tabs->selected())->toBeNull();
});

it('selects by nickname through an insertion-order index', function () {
    $log = new CallLog;
    $tabs = (new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480)))->tabs('nav', 0, 0, 360, 560);
    $tabs->page('notes', 'Notes');
    $tabs->page('picture', 'Picture');
    $log->clear();

    $tabs->select('picture');

    expect($tabs->selected())->toBe('picture')
        ->and($log->of('setSelected')[0]['args'])->toBe([1]);
});

it('removes a middle page terminally and shifts later nicknames', function () {
    $tabs = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480)))->tabs('nav', 0, 0, 360, 560);
    $a = $tabs->page('a', 'A');
    $b = $tabs->page('b', 'B');
    $c = $tabs->page('c', 'C');
    $b->label('inner', 'x', 0, 0, 10, 10);

    $tabs->removeChild('b');

    expect($tabs->count())->toBe(2)
        ->and(array_keys($tabs->pages()))->toBe(['a', 'c'])
        ->and($b->isAlive())->toBeFalse()
        ->and($tabs->find('b'))->toBeNull()
        ->and($tabs->child('c'))->toBe($c)
        ->and($a->isAlive())->toBeTrue();
});

it('routes a user switch through a trampoline', function () {
    $log = new CallLog;
    $tabs = (new FakeBox($log, 1, 'root', null, new Frame(0, 0, 640, 480)))->tabs('nav', 0, 0, 360, 560);
    $received = null;
    $tabs->onChange(function (TabsHandle $t) use (&$received): void { $received = $t; });

    $log->of('onChange')[0]['args'][0]();

    expect($received)->toBe($tabs);
});

it('refuses a missing page', function () {
    $tabs = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480)))->tabs('nav', 0, 0, 360, 560);

    $tabs->select('ghost');
})->throws(ViewException::class, 'nav.ghost');

it('removes a page through the handle, the parent or the window path alike', function (string $via) {
    $log = new CallLog;
    $window = new FakeWindow($log);
    $tabs = $window->root()->tabs('nav', 0, 0, 360, 560);
    $tabs->page('a', 'A');
    $b = $tabs->page('b', 'B');
    $inner = $b->label('inner', 'x', 0, 0, 10, 10);

    match ($via) {
        'handle' => $b->remove(),
        'parent' => $tabs->removeChild('b'),
        'window' => $window->removeFromTree('nav.b'),
    };

    expect($log->of('removePage'))->toHaveCount(1)
        ->and($b->isAlive())->toBeFalse()
        ->and($inner->isAlive())->toBeFalse()
        ->and($tabs->count())->toBe(1)
        ->and($tabs->find('b'))->toBeNull();
})->with(['handle', 'parent', 'window']);

it('forgets every page when the window closes', function () {
    $window = new FakeWindow(new CallLog);
    $tabs = $window->root()->tabs('nav', 0, 0, 360, 560);
    $a = $tabs->page('a', 'A');

    $window->close();

    expect($a->isAlive())->toBeFalse()
        ->and($tabs->isAlive())->toBeFalse()
        ->and($tabs->count())->toBe(0)
        ->and($tabs->pages())->toBe([])
        ->and(fn () => $tabs->selected())->toThrow(ViewException::class, 'nav');
});

it('keeps numeric nicknames addressable by index', function () {
    $tabs = (new FakeBox(new CallLog, 1, 'root', null, new Frame(0, 0, 640, 480)))->tabs('nav', 0, 0, 360, 560);
    $tabs->page('1', 'One');
    $tabs->page('2', 'Two');

    $tabs->select('2');

    expect($tabs->selected())->toBe('2')
        ->and(array_keys($tabs->pages()))->toBe([1, 2]);
});

<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\WindowException;
use Venusian\Surface\Tests\Fixtures\FakeHost;

it('mints a split whose children are panes and whose positions follow the visible panes', function (): void {
    $root = (new FakeHost('main'))->column('root');
    $split = $root->split('panes');
    $split->column('a');
    $split->column('b');
    $c = $split->column('c');

    expect($split->isHorizontal())->toBeTrue()
        ->and($root->split('v', false)->isHorizontal())->toBeFalse();

    $split->setPositions([0.25, 0.5]);
    expect($split->positions())->toBe([0.25, 0.5])->and($split->log)->toContain('positions:0.25,0.5');

    $c->hide();
    expect(fn () => $split->setPositions([0.25, 0.5]))->toThrow(WindowException::class, '1 divider')
        ->and(fn () => $split->setPositions([1.5]))->toThrow(WindowException::class, 'within 0..1');
    $split->setPositions([0.4]);
    expect($split->positions())->toBe([0.4]);
});

it('refuses positions that are not ascending, and a split with one visible pane has none', function (): void {
    $split = (new FakeHost('main'))->column('root')->split('panes');
    $split->column('a');
    expect($split->positions())->toBe([])
        ->and(fn () => $split->setPositions([0.5]))->toThrow(WindowException::class, '0 divider');
    $split->column('b');
    $split->column('c');
    expect(fn () => $split->setPositions([0.6, 0.3]))->toThrow(WindowException::class, 'ascend')
        ->and(fn () => $split->setPositions(['a' => 0.2, 'b' => 0.4]))->toThrow(WindowException::class, '2 divider')
        ->and(fn () => $split->setPositions([0.2, '0.4']))->toThrow(WindowException::class, 'fractions');
});

it('lets a scroll view hold a split', function (): void {
    $scroll = (new FakeHost('main'))->column('root')->scrollView('scroll');

    expect($scroll->split('panes')->path())->toBe('root.scroll.panes');
});

it('adds pages as columns, selects the first, titles and selects them, and forgets a removed page', function (): void {
    $window = new FakeHost('main');
    $tabs = $window->column('root')->tabs('sessions');
    $one = $tabs->addPage('one', 'left.txt ↔ right.txt');
    $tabs->addPage('two', 'Folders')->label('hello', 'Hello');

    expect($one)->toBeInstanceOf(TKColumn::class)
        ->and($tabs->pages())->toBe(['one', 'two'])
        ->and($tabs->selected())->toBe('one')
        ->and($tabs->title('one'))->toBe('left.txt ↔ right.txt')
        ->and($tabs->log)->toContain('page:one=left.txt ↔ right.txt', 'page:two=Folders')
        ->and($window->view('root.sessions.two.hello'))->not->toBeNull();

    $tabs->setTitle('two', 'Folders *')->select('two');
    expect($tabs->title('two'))->toBe('Folders *')->and($tabs->selected())->toBe('two')
        ->and($tabs->log)->toContain('title:two=Folders *', 'select:two');

    $one->remove();
    expect($tabs->pages())->toBe(['two'])
        ->and(fn () => $tabs->title('one'))->toThrow(WindowException::class, "no page 'one'")
        ->and(fn () => $tabs->setTitle('one', 'x'))->toThrow(WindowException::class, "no page 'one'")
        ->and(fn () => $tabs->select('nope'))->toThrow(WindowException::class, "no page 'nope'");
});

it('takes children only through addPage, and refuses a second page of the same name', function (): void {
    $tabs = (new FakeHost('main'))->column('root')->tabs('sessions');
    expect(fn () => $tabs->label('x', 'y'))->toThrow(WindowException::class, 'addPage()')
        ->and(fn () => $tabs->row('r'))->toThrow(WindowException::class, 'addPage()')
        ->and($tabs->selected())->toBeNull()
        ->and($tabs->pages())->toBe([]);

    $tabs->addPage('one', 'One');
    expect(fn () => $tabs->addPage('one', 'Again'))->toThrow(WindowException::class, "already has a child named 'one'")
        ->and($tabs->title('one'))->toBe('One')
        ->and(fn () => $tabs->label('x', 'y'))->toThrow(WindowException::class, 'addPage()');
});

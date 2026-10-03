<?php

declare(strict_types=1);

use Surface\Contracts\Windows\WindowException;
use Venusian\Surface\Tests\Fixtures\FakeHost;
use Voyager\NutsAndBolts\Str;

it('creates on containers, resolves dotted paths and uuids from the window and relative from containers', function (): void {
    $window = new FakeHost('main');
    $main = $window->column('main');
    $toolbar = $main->row('toolbar');
    $save = $toolbar->button('save', 'Save');

    expect($save->path())->toBe('main.toolbar.save')
        ->and(Str::isUuid($save->uuid()))->toBeTrue()
        ->and($window->view('main.toolbar.save'))->toBe($save)
        ->and($window->uuid($save->uuid()))->toBe($save)
        ->and($main->view('toolbar.save'))->toBe($save)
        ->and($toolbar->view('save'))->toBe($save)
        ->and($main->view('toolbar.save.deeper'))->toBeNull()
        ->and($window->view('main.nope'))->toBeNull()
        ->and($window->content())->toBe($main)
        ->and($window->mounted)->toBe(['main'])
        ->and($main->children())->toBe([$toolbar])
        ->and($save->parent())->toBe($toolbar)
        ->and($main->parent())->toBeNull()
        ->and($save->window())->toBe($window)
        ->and($toolbar->log)->toBe(['insert:main.toolbar.save']);
});

it('refuses a second content container, bad names and sibling collisions before the factory mints anything', function (): void {
    $window = new FakeHost('main');
    $main = $window->column('main');
    $main->label('a', 'A');
    $minted = $window->minter->minted;

    expect(fn () => $window->row('other'))->toThrow(WindowException::class, 'already has content container')
        ->and(fn () => $main->label('b.c', 'x'))->toThrow(WindowException::class, 'no dots')
        ->and(fn () => $main->label('', 'x'))->toThrow(WindowException::class, 'no dots')
        ->and(fn () => $main->label("b\n", 'x'))->toThrow(WindowException::class, 'no dots')
        ->and(fn () => $window->row("main\n"))->toThrow(WindowException::class)
        ->and(fn () => $main->label('a', 'again'))->toThrow(WindowException::class, "already has a child named 'a'")
        ->and($window->minter->minted)->toBe($minted)
        ->and($window->view('main.a'))->not->toBeNull()
        ->and(count($window->registry()->all()))->toBe(2);
});

it('passes a kind the engine lacks through as the factory exception, registering nothing', function (): void {
    $window = new FakeHost('main');
    $window->minter->lacking = ['Toggle'];
    $main = $window->column('main');

    expect(fn () => $main->toggle('t'))->toThrow(WindowException::class, 'TKToggle is not available on fake.')
        ->and($main->children())->toBe([])
        ->and($window->view('main.t'))->toBeNull();
});

it('removes a subtree terminally: watching stopped, natives destroyed deepest first, paths and uuids gone, later calls throw', function (): void {
    $window = new FakeHost('main');
    $main = $window->column('main');
    $box = $main->row('box');
    $label = $box->label('l', 'x')->watchSize();
    $uuid = $label->uuid();
    $box->remove();

    expect($label->isRemoved())->toBeTrue()
        ->and($label->isWatchingSize())->toBeFalse()
        ->and($label->log)->toBe(['watch:1', 'watch:0', 'destroy'])
        ->and($window->view('main.box.l'))->toBeNull()
        ->and($window->view('main.box'))->toBeNull()
        ->and($window->uuid($uuid))->toBeNull()
        ->and($main->children())->toBe([])
        ->and(array_search('destroy', $box->log, true))->not->toBeFalse()
        ->and(fn () => $label->setText('y'))->toThrow(WindowException::class, 'was removed')
        ->and(fn () => $label->size())->toThrow(WindowException::class, 'was removed')
        ->and(fn () => $label->remove())->toThrow(WindowException::class, 'was removed')
        ->and(fn () => $box->label('again', 'x'))->toThrow(WindowException::class, 'was removed');

    $main->row('box');
    expect($window->view('main.box'))->not->toBeNull();
});

it('refuses to forget a live child or content container, leaving the tree intact', function (): void {
    $window = new FakeHost('main');
    $main = $window->column('main');
    $label = $main->label('l', 'x');
    $stranger = (new FakeHost('other'))->column('main');

    expect(fn () => $window->forgetContent($main))->toThrow(WindowException::class, 'not removed')
        ->and(fn () => $window->forgetContent($stranger))->toThrow(WindowException::class)
        ->and(fn () => $main->forgetChild($label))->toThrow(WindowException::class, 'not removed')
        ->and($window->content())->toBe($main)
        ->and($main->children())->toBe([$label])
        ->and(fn () => $window->column('again'))->toThrow(WindowException::class, 'already has content container');
});

it('frees the content slot when the content container is removed, and empties the tree on close', function (): void {
    $window = new FakeHost('main');
    $window->column('main')->remove();
    $grid = $window->grid('second');
    $cell = $grid->at(0, 0)->label('c', 'C');
    $window->close();

    expect($grid->isRemoved())->toBeTrue()
        ->and($cell->isRemoved())->toBeTrue()
        ->and($window->registry()->all())->toBe([])
        ->and(fn () => $window->view('second.c'))->toThrow(WindowException::class, "Window 'main' is closed")
        ->and(fn () => $window->uuid($cell->uuid()))->toThrow(WindowException::class, 'is closed')
        ->and(fn () => $window->content())->toThrow(WindowException::class, 'is closed')
        ->and(fn () => $window->column('again'))->toThrow(WindowException::class, 'is closed');
});

<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Windows\Primitives\Placement;
use Venusian\Surface\Tests\Fixtures\FakeHost;

it('places grid children by pending cell and refuses overlaps and missing at() before minting', function (): void {
    $window = new FakeHost('main');
    $grid = $window->grid('g');
    $a = $grid->at(0, 0, 1, 2)->label('a', 'A');
    $minted = $window->minter->minted;

    expect($a->placement()->columnSpan)->toBe(2)
        ->and(fn () => $grid->label('b', 'B'))->toThrow(WindowException::class, 'needs at(')
        ->and(fn () => $grid->at(0, 1)->label('c', 'C'))->toThrow(WindowException::class, "taken by 'a'")
        ->and(fn () => $grid->label('c', 'C'))->toThrow(WindowException::class, 'needs at(')
        ->and($window->minter->minted)->toBe($minted)
        ->and($window->view('g.c'))->toBeNull()
        ->and(fn () => $grid->at(-1, 0))->toThrow(WindowException::class)
        ->and(fn () => $grid->at(0, 0, 0))->toThrow(WindowException::class);
    $grid->at(1, 0)->label('d', 'D');
    $a->remove();
    $grid->at(0, 1)->label('e', 'E');
    expect($grid->children())->toHaveCount(2)
        ->and($grid->setSpacing(4)->spacing())->toBe(4)
        ->and($grid->log)->toContain('spacing:4');
});

it('places fixed children by pending frame, moves and resizes them', function (): void {
    $window = new FakeHost('main');
    $fixed = $window->fixed('f');
    $a = $fixed->at(1, 2, 30, 40)->label('a', 'A');
    $fixed->move($a, 5, 6)->resize($a, 50, 60);
    $stranger = (new FakeHost('other'))->column('m')->label('s', 'S');

    expect([$a->placement()->x, $a->placement()->y, $a->placement()->width, $a->placement()->height])->toBe([1, 2, 30, 40])
        ->and($fixed->log)->toContain('move:a:5,6', 'resize:a:50,60')
        ->and([$fixed->frameOf($a)->x, $fixed->frameOf($a)->y, $fixed->frameOf($a)->width, $fixed->frameOf($a)->height])->toBe([5, 6, 50, 60])
        ->and(fn () => $fixed->frameOf($stranger))->toThrow(WindowException::class, 'is not a child of')
        ->and(fn () => $fixed->label('b', 'B'))->toThrow(WindowException::class, 'needs at(')
        ->and(fn () => $fixed->move($stranger, 0, 0))->toThrow(WindowException::class, 'is not a child of')
        ->and(fn () => $fixed->resize($a, 0, 10))->toThrow(WindowException::class)
        ->and(fn () => Placement::frame(0, 0, 0, 1))->toThrow(WindowException::class);
});

it('reorders a column child relative to a sibling or to an index, and refuses foreign siblings', function (): void {
    $window = new FakeHost('main');
    $col = $window->column('c');
    $a = $col->label('a', 'A');
    $b = $col->label('b', 'B');
    $c = $col->label('c', 'C');
    $c->moveBefore($a);
    $a->moveAfter($b);
    $other = $window->view('c.b')->parent()->row('r');
    $x = $other->label('x', 'X');

    expect(array_map(fn ($p) => $p->name(), $col->children()))->toBe(['c', 'b', 'a', 'r'])
        ->and($col->log)->toContain('order:c:0', 'order:a:2')
        ->and(fn () => $a->moveBefore($x))->toThrow(WindowException::class, 'is not a child of')
        ->and(fn () => $a->moveBefore($a))->toThrow(WindowException::class, 'itself')
        ->and(fn () => $a->moveTo(4))->toThrow(WindowException::class, 'out of range')
        ->and(fn () => $col->moveTo(0))->toThrow(WindowException::class, 'not in a column or row');

    $a->moveTo(0);
    expect(array_map(fn ($p) => $p->name(), $col->children()))->toBe(['a', 'c', 'b', 'r'])
        ->and($window->view('c.a'))->toBe($a);
});

it('reorders row children the same way', function (): void {
    $window = new FakeHost('main');
    $row = $window->row('r', spacing: 2, padding: 3);
    $a = $row->label('a', 'A');
    $b = $row->button('b', 'B');
    $a->moveAfter($b);
    $grid = $row->grid('g');
    $cell = $grid->at(0, 0)->label('cell', 'C');

    expect(array_map(fn ($p) => $p->name(), $row->children()))->toBe(['b', 'a', 'g'])
        ->and(fn () => $cell->moveTo(0))->toThrow(WindowException::class, 'not in a column or row')
        ->and(fn () => $cell->moveBefore($a))->toThrow(WindowException::class, 'not in a column or row')
        ->and($row->spacing())->toBe(2)
        ->and($row->log)->toContain('order:a:1')
        ->and(fn () => $window->view('r')->setSpacing(-1))->toThrow(WindowException::class);
});

it('holds exactly one container in a scroll view', function (): void {
    $window = new FakeHost('main');
    $scroll = $window->column('m')->scrollView('s');
    $minted = count($window->minter->minted);

    expect($scroll->content())->toBeNull()
        ->and(fn () => $scroll->label('leaf', 'x'))->toThrow(WindowException::class, 'exactly one container')
        ->and(fn () => $scroll->scrollView('nested'))->toThrow(WindowException::class, 'exactly one container')
        ->and(count($window->minter->minted))->toBe($minted);

    $inner = $scroll->column('inner');

    expect($scroll->content())->toBe($inner)
        ->and(fn () => $scroll->row('second'))->toThrow(WindowException::class, 'exactly one container')
        ->and($scroll->setScrollbars(false, true)->log)->toContain('scrollbars:0,1');

    $inner->remove();
    expect($scroll->content())->toBeNull();
});

it('passes properties to the engine change-only and reads size from the engine', function (): void {
    $window = new FakeHost('main');
    $label = $window->column('m')->label('a', 'A');
    $label->hide()->hide()->disable()->disable()->fill(vertical: false)->align(Align::CENTER)->minSize(20, 10)->setBackground(Color::hex('#fff'));

    expect($label->size())->toBe([10, 10])
        ->and($label->isVisible())->toBeFalse()
        ->and($label->isEnabled())->toBeFalse()
        ->and(array_count_values($label->log)['visible:0'])->toBe(1)
        ->and(array_count_values($label->log)['enabled:0'])->toBe(1)
        ->and($label->log)->toContain('fill:1,0', 'align:center,fill', 'min:20,10', 'background:#ffffff')
        ->and(fn () => $label->minSize(-1, 0))->toThrow(WindowException::class);

    $label->show()->enable()->setBackground(null);
    expect($label->log)->toContain('visible:1', 'enabled:1', 'background:none');
});

it('stores label and button state and passes each change to the engine', function (): void {
    $window = new FakeHost('main');
    $main = $window->column('m');
    $label = $main->label('l', 'one');
    $button = $main->button('b', 'Go');
    $label->setText('two');
    $button->setLabel('Stop');

    expect($label->text())->toBe('two')
        ->and($button->label())->toBe('Stop')
        ->and($label->log)->toBe(['text:two'])
        ->and($button->log)->toBe(['label:Stop']);
});

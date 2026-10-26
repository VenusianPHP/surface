<?php

declare(strict_types=1);

use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\ContextMenu;
use Venusian\Surface\Tests\Fixtures\FakeHost;

/*
 * popupMenu() and the stored menus' state, toolkit-neutral: the fake native logs the popup and
 * reports $native_shown as the toolkit's view of whether it is on screen.
 */

it('opens a menu at a point in a shown primitive, once, with the menu parsed', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('l', 'text');

    $label->popupMenu([['label' => 'Copy'], ['label' => 'Open']], 12.5, 4.0);

    expect(array_values(array_filter($label->log, fn (string $line): bool => str_starts_with($line, 'popup:'))))->toBe(['popup:copy|open@12.5,4']);
});

it('refuses to open a menu from a primitive that is hidden, under a hidden group, off screen or removed', function (): void {
    $column = (new FakeHost('main'))->column('m');
    $hidden = $column->label('hidden', 'x')->hide();
    $inner = $column->column('inner');
    $under = $inner->label('under', 'x');
    $inner->hide();
    $off = $column->label('off', 'x');
    $off->native_shown = false;
    $gone = $column->label('gone', 'x');
    $gone->remove();
    $menu = [['label' => 'Copy']];

    expect(fn () => $hidden->popupMenu($menu, 0, 0))->toThrow(WindowException::class, "'m.hidden' cannot open a menu: it is not shown.")
        ->and(fn () => $under->popupMenu($menu, 0, 0))->toThrow(WindowException::class, "'m.inner.under' cannot open a menu: it is not shown.")
        ->and(fn () => $off->popupMenu($menu, 0, 0))->toThrow(WindowException::class, "'m.off' cannot open a menu: it is not shown.")
        ->and(fn () => $gone->popupMenu($menu, 0, 0))->toThrow(WindowException::class, 'was removed');
});

it('keeps the user\'s toggle and radio choices in a stored context menu', function (): void {
    $label = (new FakeHost('main'))->column('m')->label('l', 'text');
    $label->setContextMenu([
        ['id' => 'wrap', 'label' => 'Wrap', 'toggle' => true],
        ['id' => 'case.none', 'label' => 'None', 'radio' => 'case', 'on' => true],
        ['id' => 'case.all', 'label' => 'All', 'radio' => 'case'],
    ]);

    $label->nativeContextToggled('wrap', true);
    $label->nativeContextRadio('case', 'case.all');

    expect($label->contextMenu())->toBeInstanceOf(ContextMenu::class)
        ->and($label->contextMenu()->items()['wrap']->isOn())->toBeTrue()
        ->and($label->contextMenu()->radios())->toBe(['case' => 'case.all']);
});

it('keeps the user\'s choices in a menu button\'s menu', function (): void {
    $button = (new FakeHost('main'))->column('m')->menuButton('b', 'Case', [
        ['id' => 'case.none', 'label' => 'None', 'radio' => 'case', 'on' => true],
        ['id' => 'case.all', 'label' => 'All', 'radio' => 'case'],
        ['id' => 'wrap', 'label' => 'Wrap', 'toggle' => true],
    ]);

    $button->nativeContextRadio('case', 'case.all');
    $button->nativeContextToggled('wrap', true);

    expect($button->menu()->radios())->toBe(['case' => 'case.all'])
        ->and($button->menu()->items()['wrap']->isOn())->toBeTrue();
});

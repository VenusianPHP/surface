<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Menus\ContextMenu as ContextMenuContract;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\ContextMenu;
use Venusian\Surface\Tests\Fixtures\FakeHost;

it('parses items, separators and submenus, with ids from the label path', function (): void {
    $menu = ContextMenu::parse([
        ['label' => 'Download'],
        ['id' => 'open', 'label' => 'Open in a Window'],
        ['separator' => true],
        ['label' => 'Share', 'items' => [
            ['label' => 'Copy Link'],
        ]],
    ]);

    expect($menu)->toBeInstanceOf(ContextMenuContract::class)
        ->and($menu->items)->toHaveCount(4)
        ->and($menu->items[2]->separator)->toBeTrue()
        ->and($menu->items[3]->isFolder())->toBeTrue()
        ->and(array_keys($menu->items()))->toBe(['download', 'open', 'share.copy-link']);
});

it('refuses an empty menu, roles and hotkeys, nested ones too', function (): void {
    expect(fn () => ContextMenu::parse([]))->toThrow(WindowException::class, 'at least one item')
        ->and(fn () => ContextMenu::parse([['label' => 'Quit', 'role' => 'quit']]))->toThrow(WindowException::class, "'Quit'")
        ->and(fn () => ContextMenu::parse([['label' => 'More', 'items' => [['label' => 'Save', 'hotkey' => 's']]]]))->toThrow(WindowException::class, "'Save'");
});

it('carries a context menu on a primitive, from nodes or a shared menu, and takes it off', function (): void {
    $window = new FakeHost('main');
    $column = $window->column('c');
    $button = $column->button('go', 'Go');
    $shared = ContextMenu::parse([['label' => 'Download']]);

    expect($button->contextMenu())->toBeNull()
        ->and($button->setContextMenu([['label' => 'Open']]))->toBe($button)
        ->and(array_keys($button->contextMenu()->items()))->toBe(['open'])
        ->and($column->setContextMenu($shared)->contextMenu())->toBe($shared)
        ->and($button->setContextMenu(null)->contextMenu())->toBeNull()
        ->and(fn () => $button->setContextMenu([]))->toThrow(WindowException::class, 'at least one item');

    $button->remove();
    expect(fn () => $button->setContextMenu([['label' => 'Open']]))->toThrow(WindowException::class);
});

it('refuses an edit role in a context menu, as it refuses every role', function (): void {
    expect(fn () => ContextMenu::parse([['role' => 'copy', 'label' => 'Copy']]))->toThrow(WindowException::class);
});

it('parses enabled in a context menu and refuses keys, ids used two times', function (): void {
    $menu = ContextMenu::parse([
        ['label' => 'Share', 'enabled' => false, 'items' => [['label' => 'Copy Link']]],
        ['label' => 'Open'],
    ]);

    expect($menu->nodes()['share']->enabled)->toBeFalse()
        ->and($menu->nodes()['open']->enabled)->toBeTrue()
        ->and(array_keys($menu->nodes()))->toBe(['share', 'share.copy-link', 'open'])
        ->and(fn () => ContextMenu::parse([['label' => 'Save', 'keys' => 'Primary+S']]))->toThrow(WindowException::class, "'Save'")
        ->and(fn () => ContextMenu::parse([['id' => 'x', 'label' => 'A'], ['id' => 'x', 'label' => 'B']]))->toThrow(WindowException::class, "'x'");
});

it('takes toggles and radios, and keeps the user\'s choices in copies', function (): void {
    $menu = ContextMenu::parse([
        ['id' => 'wrap', 'label' => 'Wrap', 'toggle' => true],
        ['separator' => true],
        ['id' => 'case.none', 'label' => 'None', 'radio' => 'case', 'on' => true],
        ['id' => 'case.all', 'label' => 'All', 'radio' => 'case'],
    ]);

    $wrapped = $menu->withToggle('wrap', true);
    $all = $wrapped->withRadio('case', 'case.all');

    expect($menu->items()['wrap']->isOn())->toBeFalse()
        ->and($menu->radios())->toBe(['case' => 'case.none'])
        ->and($wrapped->items()['wrap']->isOn())->toBeTrue()
        ->and($wrapped)->not->toBe($menu)
        ->and($all->radios())->toBe(['case' => 'case.all'])
        ->and($all->items()['case.none']->isOn())->toBeFalse()
        ->and($all->items()['wrap']->isOn())->toBeTrue()
        ->and($wrapped->radios())->toBe(['case' => 'case.none'])
        ->and(fn () => $menu->withToggle('case.all', true))->toThrow(WindowException::class, "No toggle item 'case.all' in the menu.")
        ->and(fn () => $menu->withRadio('case', 'wrap'))->toThrow(WindowException::class, "'wrap' is not in radio group 'case'.")
        ->and(fn () => $menu->withRadio('nope', 'wrap'))->toThrow(WindowException::class, "No radio group 'nope' in the menu.");
});

it('refuses keys on a radio in a context menu', function (): void {
    expect(fn () => ContextMenu::parse([['id' => 'a', 'label' => 'A', 'radio' => 'g', 'on' => true, 'keys' => 'Primary+1']]))
        ->toThrow(WindowException::class, 'cannot be a role or carry an accelerator');
});

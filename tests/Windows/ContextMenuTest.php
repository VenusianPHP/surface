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

it('refuses an empty menu, toggles, roles and hotkeys, nested ones too', function (): void {
    expect(fn () => ContextMenu::parse([]))->toThrow(WindowException::class, 'at least one item')
        ->and(fn () => ContextMenu::parse([['label' => 'Grid', 'toggle' => true]]))->toThrow(WindowException::class, "'Grid'")
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

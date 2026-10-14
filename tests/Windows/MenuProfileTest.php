<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Menus\MenuRole;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\MenuItem;
use Surface\Windows\Menus\MenuProfile;

function sampleProfile(): MenuProfile
{
    return MenuProfile::parse('main', [
        ['label' => 'App', 'items' => [
            ['role' => 'about', 'label' => 'About'],
            ['separator' => true],
            ['role' => MenuRole::QUIT, 'label' => 'Quit', 'hotkey' => 'q'],
        ]],
        ['label' => 'View Options', 'items' => [
            ['id' => 'grid', 'label' => 'Show Grid', 'toggle' => true, 'on' => true],
            ['label' => 'Zoom', 'items' => [
                ['label' => 'Zoom In', 'hotkey' => '+'],
            ]],
        ]],
    ]);
}

it('parses folders, roles, separators, toggles and nested folders', function (): void {
    $profile = sampleProfile();

    expect($profile->name)->toBe('main')
        ->and($profile->folders)->toHaveCount(2)
        ->and($profile->folders[0]->isFolder())->toBeTrue()
        ->and($profile->folders[0]->items[0]->role)->toBe(MenuRole::ABOUT)
        ->and($profile->folders[0]->items[1]->separator)->toBeTrue()
        ->and($profile->folders[0]->items[2]->role)->toBe(MenuRole::QUIT)
        ->and($profile->folders[0]->items[2]->hotkey)->toBe('q')
        ->and($profile->folders[1]->items[0]->toggle)->toBeTrue()
        ->and($profile->folders[1]->items[0]->on)->toBeTrue();
});

it('derives ids from the label path when none is given', function (): void {
    $items = sampleProfile()->items();

    expect(array_keys($items))->toBe(['app.about', 'app.quit', 'grid', 'view-options.zoom.zoom-in']);
});

it('lists every actionable item and the initial toggle states', function (): void {
    $profile = sampleProfile();

    expect($profile->items())->each->toBeInstanceOf(MenuItem::class)
        ->and($profile->toggles())->toBe(['grid' => true]);
});

it('rejects malformed nodes', function (array $nodes, string $message): void {
    expect(fn () => MenuProfile::parse('bad', $nodes))->toThrow(WindowException::class, $message);
})->with([
    'top-level leaf' => [[['label' => 'Loose', 'role' => 'quit']], 'must be a folder'],
    'no label' => [[['items' => [['role' => 'quit']]]], 'needs a label'],
    'unknown role' => [[['label' => 'App', 'items' => [['label' => 'X', 'role' => 'explode']]]], 'unknown role'],
    'empty folder' => [[['label' => 'App', 'items' => []]], 'non-empty items'],
    'toggle with role' => [[['label' => 'App', 'items' => [['label' => 'X', 'role' => 'quit', 'toggle' => true]]]], 'cannot be a toggle'],
    'long hotkey' => [[['label' => 'App', 'items' => [['label' => 'X', 'hotkey' => 'qq']]]], 'one character'],
]);

it('parses the edit roles, which post nothing of their own and take no hotkey', function (): void {
    $profile = MenuProfile::parse('editing', [
        ['label' => 'Edit', 'items' => [
            ['role' => 'undo', 'label' => 'Undo'],
            ['role' => 'redo', 'label' => 'Redo'],
            ['separator' => true],
            ['role' => 'cut', 'label' => 'Cut'],
            ['role' => 'copy', 'label' => 'Copy'],
            ['role' => 'paste', 'label' => 'Paste'],
            ['role' => 'select-all', 'label' => 'Select All'],
        ]],
    ]);
    $items = array_values(array_filter($profile->folders[0]->items, fn ($item): bool => ! $item->separator));

    expect(array_map(fn ($item): string => $item->id, $items))->toBe(['edit.undo', 'edit.redo', 'edit.cut', 'edit.copy', 'edit.paste', 'edit.select-all'])
        ->and(array_map(fn ($item): MenuRole => $item->role, $items))->toBe([MenuRole::UNDO, MenuRole::REDO, MenuRole::CUT, MenuRole::COPY, MenuRole::PASTE, MenuRole::SELECT_ALL])
        ->and(array_map(fn ($item): ?string => $item->hotkey, $items))->toBe([null, null, null, null, null, null])
        ->and(array_map(fn (MenuRole $role): bool => $role->isEdit(), MenuRole::cases()))->toBe([false, false, true, true, true, true, true, true]);
});

it('refuses a hotkey on an edit role: its keys are the platform\'s', function (): void {
    expect(fn () => MenuProfile::parse('editing', [
        ['label' => 'Edit', 'items' => [['role' => 'copy', 'label' => 'Copy', 'hotkey' => 'c']]],
    ]))->toThrow(WindowException::class, "Menu item 'Copy' is an edit role");
});

it('gives Surface\'s default main profile an Edit folder after the app folder', function (): void {
    $config = require dirname(__DIR__, 2).'/config/windows.php';
    $profile = MenuProfile::parse('main', $config['menus']['main']);
    $edit = $profile->folders[1];

    expect($edit->label)->toBe('Edit')
        ->and(array_values(array_filter(array_map(fn ($item): ?MenuRole => $item->role, $edit->items))))
        ->toBe([MenuRole::UNDO, MenuRole::REDO, MenuRole::CUT, MenuRole::COPY, MenuRole::PASTE, MenuRole::SELECT_ALL]);
});

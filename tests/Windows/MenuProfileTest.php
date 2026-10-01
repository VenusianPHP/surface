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

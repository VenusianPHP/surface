<?php

declare(strict_types=1);

use Surface\Contracts\Windows\Menus\MenuRole;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\Accelerator;
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
                ['label' => 'Zoom In', 'hotkey' => '='],
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
        ->and($profile->folders[0]->items[2]->keys)->toEqual(Accelerator::hotkey('q'))
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
        ->and(array_map(fn ($item): ?Accelerator => $item->keys, $items))->toBe([null, null, null, null, null, null])
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

it('parses enabled and keys on nodes', function (): void {
    $profile = MenuProfile::parse('main', [
        ['label' => 'App', 'items' => [['role' => 'quit', 'label' => 'Quit']]],
        ['label' => 'View', 'items' => [
            ['id' => 'view.refresh', 'label' => 'Refresh', 'keys' => 'Primary+Shift+F5'],
            ['id' => 'view.locked', 'label' => 'Locked', 'enabled' => false, 'items' => [['label' => 'Item']]],
        ]],
    ]);
    $nodes = $profile->nodes();

    expect($nodes['view.refresh']->keys)->toEqual(Accelerator::parse('Primary+Shift+F5'))
        ->and($nodes['view.refresh']->enabled)->toBeTrue()
        ->and($nodes['view.locked']->enabled)->toBeFalse()
        ->and(array_keys($nodes))->toBe(['app', 'app.quit', 'view', 'view.refresh', 'view.locked', 'view.locked.item']);
});

it('refuses the node rules of menu enabled state and accelerators', function (array $nodes, string $message): void {
    expect(fn () => MenuProfile::parse('bad', $nodes))->toThrow(WindowException::class, $message);
})->with([
    'hotkey and keys' => [[['label' => 'App', 'items' => [['label' => 'Save', 'hotkey' => 's', 'keys' => 'Primary+S']]]], "'Save'"],
    'keys on a folder' => [[['label' => 'App', 'items' => [['label' => 'More', 'keys' => 'Primary+M', 'items' => [['label' => 'X']]]]]], "'More'"],
    'hotkey on a folder' => [[['label' => 'App', 'items' => [['label' => 'More', 'hotkey' => 'm', 'items' => [['label' => 'X']]]]]], "'More'"],
    'keys on an edit role' => [[['label' => 'Edit', 'items' => [['role' => 'copy', 'label' => 'Copy', 'keys' => 'Primary+C']]]], "'Copy'"],
    'enabled on an edit role' => [[['label' => 'Edit', 'items' => [['role' => 'copy', 'label' => 'Copy', 'enabled' => true]]]], "'Copy'"],
    'enabled on a folder with an edit role, at depth two' => [[
        ['label' => 'App', 'items' => [['label' => 'X']]],
        ['label' => 'Tools', 'enabled' => false, 'items' => [['label' => 'Text', 'items' => [['role' => 'paste', 'label' => 'Paste']]]]],
    ], "'Tools'"],
    'enabled on the first folder' => [[['label' => 'App', 'enabled' => false, 'items' => [['label' => 'X']]]], "'App'"],
    'two nodes with one id' => [[['label' => 'App', 'items' => [['id' => 'same', 'label' => 'X'], ['id' => 'same', 'label' => 'Y', 'items' => [['label' => 'Z']]]]]], "'same'"],
    'one accelerator on two items' => [[['label' => 'App', 'items' => [['label' => 'Reload', 'keys' => 'Primary+R'], ['label' => 'Refresh', 'hotkey' => 'r']]]], "'Refresh'"],
    'the accelerator of an edit role' => [[['label' => 'Edit', 'items' => [['role' => 'copy', 'label' => 'Copy'], ['label' => 'Clone', 'keys' => 'Primary+C']]]], "'Clone'"],
    'Primary and Ctrl, the same keys on other platforms' => [[['label' => 'App', 'items' => [['label' => 'Cut Line', 'keys' => 'Primary+X'], ['label' => 'Cross', 'keys' => 'Ctrl+X']]]], "'Cross'"],
    'an accelerator that does not parse' => [[['label' => 'App', 'items' => [['label' => 'Go', 'keys' => 'Primary+INSERT']]]], "'Go'"],
]);

it('refuses node values of the wrong type and keys on a separator', function (array $nodes, string $message): void {
    expect(fn () => MenuProfile::parse('bad', $nodes))->toThrow(WindowException::class, $message);
})->with([
    'enabled as a string' => [[['label' => 'App', 'items' => [['label' => 'Go', 'enabled' => 'false']]]], "'Go'"],
    'enabled as null' => [[['label' => 'App', 'items' => [['label' => 'Go', 'enabled' => null]]]], "'Go'"],
    'keys as an int' => [[['label' => 'App', 'items' => [['label' => 'Go', 'keys' => 5]]]], "'Go'"],
    'separator with enabled' => [[['label' => 'App', 'items' => [['label' => 'Go'], ['separator' => true, 'enabled' => false]]]], 'separator'],
    'separator with keys' => [[['label' => 'App', 'items' => [['label' => 'Go'], ['separator' => true, 'keys' => 'Primary+S']]]], 'separator'],
]);

it('takes an Accelerator for keys, as it takes a MenuRole for role', function (): void {
    $profile = MenuProfile::parse('main', [['label' => 'App', 'items' => [['label' => 'Go', 'keys' => Accelerator::parse('Primary+G')]]]]);

    expect($profile->nodes()['app.go']->keys)->toEqual(Accelerator::parse('Primary+G'));
});

it('names the profile and both labels when two nodes have one id', function (): void {
    expect(fn () => MenuProfile::parse('editor', [['label' => 'App', 'items' => [['id' => 'same', 'label' => 'First'], ['id' => 'same', 'label' => 'Second']]]]))
        ->toThrow(WindowException::class, "Menu profile 'editor': nodes 'First' and 'Second' have the same id 'same'.");
});

/** A bar with a radio group 'ignore-case' in View, 'none' on. */
function radioNodes(array $none = [], array $unimportant = []): array
{
    return [
        ['label' => 'App', 'items' => [['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q']]],
        ['label' => 'View', 'items' => [
            ['id' => 'case.none', 'label' => 'None', 'radio' => 'ignore-case', 'on' => true, ...$none],
            ['id' => 'case.unimportant', 'label' => 'Unimportant', 'radio' => 'ignore-case', ...$unimportant],
            ['id' => 'case.all', 'label' => 'All', 'radio' => 'ignore-case'],
        ]],
    ];
}

it('parses radio items with their group, the one on, hotkeys and enabled', function (): void {
    $profile = MenuProfile::parse('main', radioNodes(['hotkey' => '1'], ['enabled' => false]));
    $none = $profile->items()['case.none'];

    expect($none->radio())->toBe('ignore-case')
        ->and($none->isOn())->toBeTrue()
        ->and($none->toggle)->toBeFalse()
        ->and($none->keys)->not->toBeNull()
        ->and($profile->items()['case.unimportant']->enabled)->toBeFalse()
        ->and($profile->items()['case.all']->isOn())->toBeFalse()
        ->and($profile->radios())->toBe(['ignore-case' => 'case.none'])
        ->and($profile->radioItems('ignore-case'))->toBe(['case.none', 'case.unimportant', 'case.all'])
        ->and($profile->items()['case.all']->radio())->toBe('ignore-case');
});

it('refuses a radio with an empty group, one that is also a toggle, a role or a folder, and on for a plain item', function (): void {
    expect(fn () => MenuItem::fromArray(['id' => 'r', 'label' => 'R', 'radio' => ''], ''))->toThrow(WindowException::class, "Menu item 'r': a radio group name must be a non-empty string.")
        ->and(fn () => MenuItem::fromArray(['id' => 'r', 'label' => 'R', 'radio' => 7], ''))->toThrow(WindowException::class, "Menu item 'r': a radio group name must be a non-empty string.")
        ->and(fn () => MenuItem::fromArray(['id' => 'r', 'label' => 'R', 'radio' => 'g', 'toggle' => true], ''))->toThrow(WindowException::class, "Menu item 'r' cannot be a radio and a toggle, a role or a folder.")
        ->and(fn () => MenuItem::fromArray(['id' => 'r', 'label' => 'R', 'radio' => 'g', 'role' => 'quit'], ''))->toThrow(WindowException::class, "Menu item 'r' cannot be a radio and a toggle, a role or a folder.")
        ->and(fn () => MenuItem::fromArray(['id' => 'r', 'label' => 'R', 'radio' => 'g', 'items' => [['label' => 'A']]], ''))->toThrow(WindowException::class, "Menu item 'r' cannot be a radio and a toggle, a role or a folder.")
        ->and(fn () => MenuItem::fromArray(['id' => 'p', 'label' => 'P', 'on' => true], ''))->toThrow(WindowException::class, "Menu item 'p': 'on' needs a toggle or a radio.");
});

it('refuses a radio group split over folders, with no item or two items on, or named as an item', function (): void {
    $split = [
        ['label' => 'App', 'items' => [['label' => 'About']]],
        ['label' => 'A', 'items' => [['id' => 'pick.a', 'label' => 'A', 'radio' => 'g', 'on' => true]]],
        ['label' => 'B', 'items' => [['id' => 'pick.b', 'label' => 'B', 'radio' => 'g']]],
    ];

    expect(fn () => MenuProfile::parse('main', $split))->toThrow(WindowException::class, "Radio group 'g' must sit in one folder.")
        ->and(fn () => MenuProfile::parse('main', radioNodes(['on' => false])))->toThrow(WindowException::class, "Radio group 'ignore-case' needs exactly one item on.")
        ->and(fn () => MenuProfile::parse('main', radioNodes([], ['on' => true])))->toThrow(WindowException::class, "Radio group 'ignore-case' needs exactly one item on.");
    $named = radioNodes();
    $named[1]['items'] = array_map(fn (array $node): array => [...$node, 'radio' => 'case.all'], $named[1]['items']);
    expect(fn () => MenuProfile::parse('main', $named))->toThrow(WindowException::class, "Radio group 'case.all' is also an item id.");
});

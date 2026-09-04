<?php

use Surface\Contracts\NativeWindows\WindowableException;
use Surface\NativeWindows\Enums\MenuRole;
use Surface\NativeWindows\Menus\MenuItemSpec;
use Venusian\Surface\Tests\Bridge\Fakes\FakeSession;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindowDriver;

/*
|--------------------------------------------------------------------------
| Menu profiles
|--------------------------------------------------------------------------
|
| Profiles are engine-neutral: parsed once at registration into MenuItemSpec
| trees, stored by name on the shuttle, elected by name on a window. Engines
| receive specs, never sketch arrays, so all of this is provable with fakes.
|
*/

function demoProfile(): array
{
    return [
        ['label' => 'File', 'items' => [
            ['label' => 'Quit', 'role' => MenuRole::QUIT, 'hotkey' => 'q'],
        ]],
        ['label' => 'Tools', 'items' => [
            ['id' => 'tools.do-thing', 'label' => 'Do Thing', 'event' => 'do-thing'],
        ]],
    ];
}

// ── Parsing ────────────────────────────────────────────────────────────

it('parses folders, roles, events and hotkeys into a spec tree', function () {
    $spec = MenuItemSpec::parseList(demoProfile());

    expect($spec)->toHaveCount(2)
        ->and($spec[0]->isFolder())->toBeTrue()
        ->and($spec[0]->label)->toBe('File')
        ->and($spec[0]->items[0]->role)->toBe(MenuRole::QUIT)
        ->and($spec[0]->items[0]->hotkey)->toBe('q')
        ->and($spec[1]->items[0]->event)->toBe('do-thing');
});

it('derives an id from the label path when none is given', function () {
    $spec = MenuItemSpec::parseList(demoProfile());

    expect($spec[0]->items[0]->id)->toBe('file.quit');
});

it('keeps an explicit id over the derived one', function () {
    $spec = MenuItemSpec::parseList(demoProfile());

    expect($spec[1]->items[0]->id)->toBe('tools.do-thing');
});

it('parses a separator node', function () {
    $spec = MenuItemSpec::parseList([['separator' => true]]);

    expect($spec[0]->separator)->toBeTrue();
});

it('parses nested folders recursively', function () {
    $spec = MenuItemSpec::parseList([
        ['label' => 'Tools', 'items' => [
            ['label' => 'More', 'items' => [
                ['label' => 'Deep', 'event' => 'deep'],
            ]],
        ]],
    ]);

    expect($spec[0]->items[0]->isFolder())->toBeTrue()
        ->and($spec[0]->items[0]->items[0]->id)->toBe('tools.more.deep');
});

it('accepts a role given as its backing string', function () {
    $spec = MenuItemSpec::parseList([['label' => 'Quit', 'role' => 'quit']]);

    expect($spec[0]->role)->toBe(MenuRole::QUIT);
});

it('rejects a node with no label that is not a separator', function () {
    expect(fn () => MenuItemSpec::parseList([['role' => MenuRole::QUIT]]))
        ->toThrow(WindowableException::class, 'no label');
});

it('rejects an unknown role name', function () {
    expect(fn () => MenuItemSpec::parseList([['label' => 'X', 'role' => 'explode']]))
        ->toThrow(WindowableException::class, 'unknown role');
});

it('rejects an event that is not a non-empty string', function () {
    expect(fn () => MenuItemSpec::parseList([['label' => 'X', 'event' => '']]))
        ->toThrow(WindowableException::class, 'non-empty string');
});

it('rejects a node that is neither folder, role, nor event', function () {
    expect(fn () => MenuItemSpec::parseList([['label' => 'Nothing']]))
        ->toThrow(WindowableException::class);
});

// ── Registration on the application ────────────────────────────────────────

function menuApp(): \Surface\Core\LiveApplication
{
    return liveApp()[0];
}

it('parses profiles at registration and hands back spec trees by name', function () {
    $program = menuApp();

    $program->addMenuBarProfiles(['main_menu' => demoProfile()]);

    $spec = $program->getMenuBarProfile('main_menu');
    expect($spec)->toHaveCount(2)
        ->and($spec[0])->toBeInstanceOf(MenuItemSpec::class);
});

it('answers null for a profile that was never registered', function () {
    expect(menuApp()->getMenuBarProfile('nope'))->toBeNull();
});

it('returns the application from registration so calls can chain', function () {
    $program = menuApp();

    expect($program->addMenuBarProfiles(['a' => demoProfile()]))->toBe($program);
});

it('refuses a malformed profile at registration, not at election', function () {
    expect(fn () => menuApp()->addMenuBarProfiles(['bad' => [['label' => 'Nothing']]]))
        ->toThrow(WindowableException::class);
});

// ── Election on a window ───────────────────────────────────────────────

it('resolves the elected profile and hands the spec tree to the engine hook', function () {
    $window = new FakeWindow('main');
    $window->known_profiles['main_menu'] = MenuItemSpec::parseList(demoProfile());

    $result = $window->setMenuBar('main_menu');

    expect($result)->toBe($window)
        ->and($window->applied_menu_bars)->toHaveCount(1)
        ->and($window->applied_menu_bars[0][1]->label)->toBe('Tools');
});

it('refuses to elect a profile that is not registered', function () {
    $window = new FakeWindow('main');

    expect(fn () => $window->setMenuBar('ghost'))
        ->toThrow(WindowableException::class, "Menu bar profile 'ghost' is not registered.");
});

it('re-election applies again rather than caching the first tree', function () {
    $window = new FakeWindow('main');
    $window->known_profiles['a'] = MenuItemSpec::parseList(demoProfile());
    $window->known_profiles['b'] = MenuItemSpec::parseList([['label' => 'Other', 'items' => []]]);

    $window->setMenuBar('a');
    $window->setMenuBar('b');

    expect($window->applied_menu_bars)->toHaveCount(2)
        ->and($window->applied_menu_bars[1][0]->label)->toBe('Other');
});

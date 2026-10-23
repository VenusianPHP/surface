<?php

declare(strict_types=1);

use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\ContextMenu;
use Surface\Windows\Menus\MenuProfile;
use Surface\Windows\Menus\MenuStates;
use Venusian\Surface\Tests\Fixtures\FakeHost;
use Venusian\Surface\Tests\Fixtures\FakeWindowDriver;

function statesProfile(): MenuProfile
{
    return MenuProfile::parse('main', [
        ['label' => 'App', 'items' => [['role' => 'quit', 'label' => 'Quit', 'hotkey' => 'q']]],
        ['label' => 'View', 'items' => [
            ['id' => 'view.refresh', 'label' => 'Refresh'],
            ['id' => 'view.more', 'label' => 'More', 'items' => [
                ['id' => 'view.more.deep', 'label' => 'Deep'],
            ]],
            ['id' => 'view.off', 'label' => 'Off', 'enabled' => false],
        ]],
        ['label' => 'Edit', 'items' => [
            ['role' => 'copy', 'label' => 'Copy'],
            ['id' => 'edit.find', 'label' => 'Find'],
        ]],
    ]);
}

it('takes the own state of each node from the profile, enabled by default', function (): void {
    $states = new MenuStates(statesProfile());

    expect($states->isEnabled('view.refresh'))->toBeTrue()
        ->and($states->isEnabled('view'))->toBeTrue()
        ->and($states->isEnabled('view.off'))->toBeFalse()
        ->and($states->effective('view.off'))->toBeFalse();
});

it('makes the nodes below a disabled folder not effective, and keeps their own state', function (): void {
    $states = new MenuStates(statesProfile());
    $states->set('view', false);

    expect($states->effective('view.more.deep'))->toBeFalse()
        ->and($states->isEnabled('view.more.deep'))->toBeTrue()
        ->and($states->isEnabled('view'))->toBeFalse();
});

it('restores the own state of each child when its folder is enabled again', function (): void {
    $states = new MenuStates(statesProfile());
    $states->set('view.more.deep', false);
    $states->set('view.more', false);
    $states->set('view.more', true);

    expect($states->effective('view.more.deep'))->toBeFalse()
        ->and($states->effective('view.more'))->toBeTrue()
        ->and($states->effective('view.refresh'))->toBeTrue();
});

it('gives a node and every node below it, in tree order', function (): void {
    expect((new MenuStates(statesProfile()))->subtree('view'))->toBe(['view', 'view.refresh', 'view.more', 'view.more.deep', 'view.off'])
        ->and((new MenuStates(statesProfile()))->subtree('view.refresh'))->toBe(['view.refresh']);
});

it('refuses an unknown id, an edit role, a folder with an edit role and the first folder', function (string $id, string $message): void {
    $states = new MenuStates(statesProfile());

    expect(fn () => $states->set($id, false))->toThrow(WindowException::class, $message);
})->with([
    'unknown' => ['nope', "'nope'"],
    'edit role' => ['edit.copy', "'Copy'"],
    'folder with an edit role' => ['edit', "'Edit'"],
    'first folder' => ['app', "'App'"],
]);

it('takes a state for an item in a folder with an edit role, and reads unknown ids as an error', function (): void {
    $states = new MenuStates(statesProfile());
    $states->set('edit.find', false);

    expect($states->effective('edit.find'))->toBeFalse()
        ->and(fn () => $states->isEnabled('nope'))->toThrow(WindowException::class, "'nope'")
        ->and(fn () => $states->effective('nope'))->toThrow(WindowException::class, "'nope'");
});

it('takes a state for any node of a context menu, the first folder too', function (): void {
    $states = new MenuStates(ContextMenu::parse([
        ['label' => 'Share', 'items' => [['label' => 'Copy Link']]],
        ['label' => 'Open'],
    ]));
    $states->set('share', false);

    expect($states->effective('share.copy-link'))->toBeFalse()
        ->and($states->effective('open'))->toBeTrue();
});

it('keeps the state on the fake window, and refuses a window with no menu bar', function (): void {
    $window = (new FakeWindowDriver())->open('main', 100, 100, statesProfile());

    expect($window->setItemEnabled('view.refresh', false))->toBe($window)
        ->and($window->isItemEnabled('view.refresh'))->toBeFalse()
        ->and(fn () => (new FakeWindowDriver())->open('bare', 100, 100)->setItemEnabled('view.refresh', false))->toThrow(WindowException::class, 'no menu bar')
        ->and(fn () => (new FakeHost('host'))->isItemEnabled('view.refresh'))->toThrow(WindowException::class, 'no menu bar');
});

it('refuses to read the state of an edit role or the first folder, which the toolkit owns', function (string $id, string $message): void {
    $states = new MenuStates(statesProfile());

    expect(fn () => $states->isEnabled($id))->toThrow(WindowException::class, $message);
})->with([
    'edit role' => ['edit.copy', "'Copy'"],
    'folder with an edit role' => ['edit', "'Edit'"],
    'first folder' => ['app', "'App'"],
]);

it('gives the states that differ from the profile, for a bar built later', function (): void {
    $states = new MenuStates(statesProfile());
    $states->set('view.refresh', false);
    $states->set('view.off', true);
    $states->set('view.more', false);
    $states->set('view.more', true);

    expect($states->changes())->toBe(['view.refresh' => false, 'view.off' => true]);
});

<?php

declare(strict_types=1);

use Surface\Contracts\Windows\WindowException;
use Surface\Windows\ChildWindows;
use Surface\Windows\Menus\MenuProfile;
use Venusian\Surface\Tests\Fixtures\FakeWindowDriver;

it('opens a child with a parent', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $child = $driver->open('child', 300, 120, null, $main);

    expect($child->parent())->toBe($main)
        ->and($main->parent())->toBeNull()
        ->and(ChildWindows::childOf($driver, $main))->toBe($child)
        ->and(ChildWindows::childOf($driver, $child))->toBeNull();
});

it('refuses a parent that cannot take a child', function (string $case): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $parent = $main;
    if ($case === 'closed') {
        $main->close();
    } elseif ($case === 'other driver') {
        $parent = (new FakeWindowDriver())->open('main', 400, 300);
    } elseif ($case === 'child') {
        $parent = $driver->open('child', 300, 120, null, $main);
    } elseif ($case === 'open child') {
        $driver->open('child', 300, 120, null, $main);
    }
    $menu = $case === 'menu' ? MenuProfile::parse('main', [['label' => 'App', 'items' => [['label' => 'Go']]]]) : null;

    expect(fn () => $driver->open('next', 300, 120, $menu, $parent))->toThrow(WindowException::class, "'{$parent->name()}'");
})->with(['closed', 'other driver', 'child', 'open child', 'menu']);

it('refuses a menu bar for a child', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $child = $driver->open('child', 300, 120, null, $main);

    expect(fn () => $child->setMenuBar('main'))->toThrow(WindowException::class, "Window 'child' is a child, so it cannot have a menu bar.")
        ->and($main->setMenuBar('main'))->toBe($main);
});

it('opens a new child after the first child closes', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $driver->open('child', 300, 120, null, $main)->close();

    expect(ChildWindows::childOf($driver, $main))->toBeNull()
        ->and($driver->open('again', 300, 120, null, $main)->parent())->toBe($main);
});

it('keeps the close confirmation', function (): void {
    $main = (new FakeWindowDriver())->open('main', 400, 300);

    expect($main->isConfirmingClose())->toBeFalse()
        ->and($main->confirmClose())->toBe($main)
        ->and($main->isConfirmingClose())->toBeTrue()
        ->and($main->confirmClose(false)->isConfirmingClose())->toBeFalse();
});

it('closes the child first when the parent closes', function (): void {
    $driver = new FakeWindowDriver();
    $main = $driver->open('main', 400, 300);
    $child = $driver->open('child', 300, 120, null, $main);
    $main->close();

    expect($child->isOpen())->toBeFalse()
        ->and($driver->closed)->toBe(['child', 'main']);
});

<?php

declare(strict_types=1);

use Surface\Bridge\ToolkitManager;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\Menus\MenuProfile;
use Surface\Windows\ToolkitWindowManager;
use Venusian\Surface\Tests\Fixtures\FakeWindowDriver;

/** A toolkit manager that hands out the given driver without a container. */
function toolkitsReturning(object $driver): ToolkitManager
{
    return new class($driver) extends ToolkitManager {
        public function __construct(private readonly object $fake) {}

        public function driver(?string $driver = null): mixed { return $this->fake; }
    };
}

function menus(): array
{
    return [
        'main' => [['label' => 'App', 'items' => [['role' => 'quit', 'label' => 'Quit']]]],
        'editor' => [['label' => 'File', 'items' => [['label' => 'Save', 'hotkey' => 's']]]],
    ];
}

it('parses every configured profile up front', function (): void {
    $manager = new ToolkitWindowManager(toolkitsReturning(new FakeWindowDriver()), menus(), 'main');

    expect($manager->profile('main'))->toBeInstanceOf(MenuProfile::class)
        ->and($manager->profile('editor')->items())->toHaveKey('file.save')
        ->and(fn () => $manager->profile('nope'))->toThrow(WindowException::class, "No menu profile named 'nope'");
});

it('opens through the toolkit driver with the default bar and the chosen profile', function (): void {
    $driver = new FakeWindowDriver();
    $manager = new ToolkitWindowManager(toolkitsReturning($driver), menus(), 'main');

    $window = $manager->open('editor-1', 640, 480, 'editor');

    expect($window->name())->toBe('editor-1')
        ->and($driver->default_menu)->toBe($manager->profile('main'))
        ->and($driver->last_menu)->toBe($manager->profile('editor'))
        ->and($manager->get('editor-1'))->toBe($window)
        ->and($manager->all())->toBe(['editor-1' => $window]);

    $manager->closeAll();
    expect($driver->closed_all)->toBe(1);
});

it('opens without a bar and without a default when none is configured', function (): void {
    $driver = new FakeWindowDriver();
    $manager = new ToolkitWindowManager(toolkitsReturning($driver), [], null);

    $manager->open('bare', 320, 200);

    expect($driver->default_menu)->toBeNull()
        ->and($driver->last_menu)->toBeNull();
});

it('refuses a toolkit driver that does not open windows', function (): void {
    $manager = new ToolkitWindowManager(toolkitsReturning(new stdClass()), [], null);

    expect(fn () => $manager->open('x', 1, 1))->toThrow(WindowException::class, 'does not open windows');
});

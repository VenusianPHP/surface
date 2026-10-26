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
        'tools' => [
            ['label' => 'File', 'items' => [['label' => 'Save']]],
            ['label' => 'View', 'items' => [
                ['id' => 'view.grid', 'label' => 'Grid', 'toggle' => true],
                ['id' => 'zoom.100', 'label' => '100%', 'radio' => 'zoom', 'on' => true],
                ['id' => 'zoom.200', 'label' => '200%', 'radio' => 'zoom'],
            ]],
        ],
    ];
}

it('gives the driver the parent of a child', function (): void {
    $manager = new ToolkitWindowManager(toolkitsReturning(new FakeWindowDriver()), menus(), 'main');
    $main = $manager->open('main', 640, 480);

    expect($manager->open('child', 300, 120, null, $main)->parent())->toBe($main);
});

it('gives the services of the driver', function (): void {
    $driver = new FakeWindowDriver();
    $manager = new ToolkitWindowManager(toolkitsReturning($driver), menus(), 'main');

    expect($manager->dialogs())->toBe($driver->dialogs())
        ->and($manager->clipboard())->toBe($driver->clipboard())
        ->and($manager->desktop())->toBe($driver->desktop());
});

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

it('gives the driver the default profile, then sets and reads a state of the default bar', function (): void {
    $driver = new FakeWindowDriver();
    $manager = new ToolkitWindowManager(toolkitsReturning($driver), menus(), 'editor');

    $manager->setItemEnabled('file.save', false);

    expect($driver->default_menu)->toBe($manager->profile('editor'))
        ->and($driver->isItemEnabled('file.save'))->toBeFalse()
        ->and($manager->isItemEnabled('file.save'))->toBeFalse();
});

it('refuses a default bar state when no default menu is configured', function (): void {
    $manager = new ToolkitWindowManager(toolkitsReturning(new FakeWindowDriver()), menus(), null);

    expect(fn () => $manager->setItemEnabled('file.save', false))->toThrow(WindowException::class, 'default menu')
        ->and(fn () => $manager->isItemEnabled('file.save'))->toThrow(WindowException::class, 'default menu');
});

it('sets and reads toggles, radios and labels of the default bar through the driver', function (): void {
    $driver = new FakeWindowDriver();
    $manager = new ToolkitWindowManager(toolkitsReturning($driver), menus(), 'tools');

    $manager->setToggle('view.grid', true);
    $manager->setRadio('zoom', 'zoom.200');
    $manager->setItemLabel('file.save', 'Save All');

    expect($manager->isToggled('view.grid'))->toBeTrue()
        ->and($driver->isToggled('view.grid'))->toBeTrue()
        ->and($manager->radio('zoom'))->toBe('zoom.200')
        ->and($driver->radio('zoom'))->toBe('zoom.200')
        ->and($manager->itemLabel('file.save'))->toBe('Save All')
        ->and($driver->itemLabel('file.save'))->toBe('Save All');
});

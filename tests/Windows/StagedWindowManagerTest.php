<?php

declare(strict_types=1);

use Surface\Bridge\ToolkitManager;
use Surface\Contracts\Windows\WindowException;
use Surface\Windows\StagedWindowManager;
use Venusian\Surface\Tests\Fixtures\FakeSession;

/** A toolkit manager that hands out the given drivers by name without a container: stand-in for the extend() registrations a stager package makes from its provider. */
function stagersNamed(array $drivers, string $default): ToolkitManager
{
    return new class($drivers, $default) extends ToolkitManager {
        public function __construct(private readonly array $fakes, private readonly string $default) {}

        public function driver(?string $driver = null): mixed
        {
            $driver ??= $this->default;

            return $this->fakes[$driver] ?? throw new InvalidArgumentException("Driver [$driver] not supported.");
        }

        public function getDefaultDriver(): string { return $this->default; }
    };
}

it('opens through the configured stager and finds the window again', function (): void {
    $one = new FakeStager(new FakeSession());
    $manager = new StagedWindowManager(stagersNamed(['one' => $one], 'none'), 'one');

    $window = $manager->open('game', 320, 240, ['title' => 'Doom']);

    expect($window->title())->toBe('Doom')
        ->and($one->hasStaged('game'))->toBeTrue()
        ->and($manager->get('game'))->toBe($window)
        ->and($manager->all())->toBe(['game' => $window]);
});

it('opens through the stager the toolkit option names, and strips the option', function (): void {
    $one = new FakeStager(new FakeSession());
    $two = new FakeStager(new FakeSession());
    $manager = new StagedWindowManager(stagersNamed(['one' => $one, 'two' => $two], 'none'), 'one');

    $window = $manager->open('tool', 100, 100, ['toolkit' => 'two']);

    expect($two->hasStaged('tool'))->toBeTrue()
        ->and($one->hasStaged('tool'))->toBeFalse()
        ->and($manager->get('tool'))->toBe($window);
});

it('refuses a name another stager already opened', function (): void {
    $manager = new StagedWindowManager(stagersNamed(['one' => new FakeStager(new FakeSession()), 'two' => new FakeStager(new FakeSession())], 'none'), 'one');
    $manager->open('game', 1, 1);

    expect(fn () => $manager->open('game', 1, 1, ['toolkit' => 'two']))
        ->toThrow(WindowException::class, "A staged window named 'game' is already open (through the 'one' stager).");
});

it('forgets a window its stager closed, and closes every window of every stager used', function (): void {
    $one = new FakeStager(new FakeSession());
    $two = new FakeStager(new FakeSession());
    $manager = new StagedWindowManager(stagersNamed(['one' => $one, 'two' => $two], 'none'), 'one');
    $a = $manager->open('a', 1, 1);
    $b = $manager->open('b', 1, 1, ['toolkit' => 'two']);

    $a->close();
    expect($manager->get('a'))->toBeNull()
        ->and($manager->all())->toBe(['b' => $b]);

    $manager->closeAll();
    expect($b->isOpen())->toBeFalse()
        ->and($manager->all())->toBe([]);
});

it('refuses a toolkit driver that does not stage windows', function (): void {
    $manager = new StagedWindowManager(stagersNamed(['plain' => new stdClass()], 'none'), 'plain');

    expect(fn () => $manager->open('x', 1, 1))->toThrow(WindowException::class, 'stdClass does not stage windows.');
});

it('refuses to open without a configured stager', function (): void {
    $manager = new StagedWindowManager(stagersNamed([], 'none'), null);

    expect(fn () => $manager->open('x', 1, 1))
        ->toThrow(WindowException::class, "No stager configured: set bridge.stage.<os>.default, or pass ['toolkit' => …] to open().")
        ->and(fn () => $manager->driver())
        ->toThrow(WindowException::class, "No stager configured: set bridge.stage.<os>.default, or pass ['toolkit' => …] to open().");
});

it('refuses a toolkit option that is not a string', function (): void {
    $manager = new StagedWindowManager(stagersNamed(['one' => new FakeStager(new FakeSession())], 'none'), 'one');

    expect(fn () => $manager->open('x', 1, 1, ['toolkit' => 3]))
        ->toThrow(WindowException::class, "Staged window 'x' option 'toolkit' is a string, got int.");
});

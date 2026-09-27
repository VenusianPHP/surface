<?php

use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\EmbeddedDisplays\EmbeddedDisplayException;
use Venusian\Surface\Tests\Support\Fakes\FakeCircuitRegistry;
use Venusian\Surface\Tests\Support\Fakes\FakeDisplayPanel;

/**
 * A manager whose vessel also carries a circuit catalog holding the given chips.
 *
 * @param  array<string, object>  $chips
 * @return array{\Surface\EmbeddedDisplays\EmbeddedDisplayManager, FakeCircuitRegistry}
 */
function panelManager(array $chips): array
{
    $registry = new FakeCircuitRegistry($chips);
    [$manager] = displayManager([], ['circuit' => $registry]);

    return [$manager, $registry];
}

it('conjures a panel from the chip default config and attaches it under the chip name', function () {
    $panel = new FakeDisplayPanel(16, 16, oledSpec());
    [$manager, $registry] = panelManager(['st7789' => $panel]);

    $display = $manager->panel('st7789');

    expect($registry->asked)->toBe([['st7789', null]])
        ->and($display->panel())->toBe($panel)
        ->and($display->name())->toBe('st7789')
        ->and($display->engine())->toBe(CPUEngine::DIRTY)
        ->and($manager->display('st7789'))->toBe($display);
});

it('conjures once: asking again answers the same display and leaves the bus alone', function () {
    [$manager, $registry] = panelManager(['st7789' => new FakeDisplayPanel(16, 16, oledSpec())]);

    expect($manager->panel('st7789'))->toBe($manager->panel('st7789'))
        ->and($registry->asked)->toHaveCount(1);
});

it('takes a named config, its own display name, and an engine', function () {
    [$manager, $registry] = panelManager([
        'st7789.left' => new FakeDisplayPanel(16, 16, oledSpec()),
        'st7789.right' => new FakeDisplayPanel(16, 16, oledSpec()),
    ]);

    $left = $manager->panel('st7789', 'left');
    $right = $manager->panel('st7789', 'right', name: 'hud', engine: CPUEngine::FULL);

    expect($left->name())->toBe('st7789.left')
        ->and($right->name())->toBe('hud')
        ->and($right->engine())->toBe(CPUEngine::FULL)
        ->and($registry->asked)->toBe([['st7789', 'left'], ['st7789', 'right']]);
});

it('refuses a chip that is not a display panel', function () {
    $thermometer = new class implements GeneralPurposeIO\Contracts\IntegratedCircuits\IntegratedCircuit {};
    [$manager] = panelManager(['aht20' => $thermometer]);

    expect(fn () => $manager->panel('aht20'))->toThrow(EmbeddedDisplayException::class, 'is not a display panel');
});

it('says so when no circuit catalog is installed', function () {
    [$manager] = displayManager();

    expect(fn () => $manager->panel('st7789'))->toThrow(EmbeddedDisplayException::class, 'No circuit catalog');
});

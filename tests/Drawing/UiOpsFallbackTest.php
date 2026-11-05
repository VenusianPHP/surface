<?php

declare(strict_types=1);

use Surface\Contracts\Rasterize\FillRule;
use Surface\NutsAndBolts\Color;

/*
 * The UI ops every engine has through the base class: roundRect and shadow,
 * lowered to path commands built from fillPath.
 */

/** @return array{float, float, float, float} min x, min y, max x, max y of one contour */
function contourBox(array $contour): array
{
    $xs = array_column($contour, 0);
    $ys = array_column($contour, 1);

    return [min($xs), min($ys), max($xs), max($ys)];
}

it('fills a rounded rect with one path whose box is the rect', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($e) => $e->roundRect(10, 20, 40, 30, [4, 4, 4, 4], Color::rgb(255, 0, 0)));

    expect($commands)->toHaveCount(1)
        ->and($commands[0][0])->toBe('path')
        ->and($commands[0][1])->toHaveCount(1)
        ->and(contourBox($commands[0][1][0]))->toEqual([10.0, 20.0, 50.0, 50.0])
        ->and($commands[0][3])->toBe(0xFF0000FF);
});

it('draws an inside border as an even-odd outer-minus-inner pair over an inset fill', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($e) => $e->roundRect(10, 20, 40, 30, [4, 4, 4, 4], Color::rgb(255, 0, 0), Color::rgb(0, 0, 255), 2));

    expect($commands)->toHaveCount(2);
    [$fill, $border] = $commands;
    expect(contourBox($fill[1][0]))->toEqual([12.0, 22.0, 48.0, 48.0])
        ->and($border[1])->toHaveCount(2)
        ->and($border[2])->toBe(FillRule::EVEN_ODD)
        ->and($border[3])->toBe(0x0000FFFF)
        ->and(contourBox($border[1][0]))->toEqual([10.0, 20.0, 50.0, 50.0])
        ->and(contourBox($border[1][1]))->toEqual([12.0, 22.0, 48.0, 48.0]);
});

it('clamps radii to half the short side', function () {
    $engine = new RecordingEngine();
    $big = $engine->commandsOf(fn ($e) => $e->roundRect(0, 0, 40, 20, [99, 99, 99, 99], Color::rgb(1, 2, 3)));
    $half = $engine->commandsOf(fn ($e) => $e->roundRect(0, 0, 40, 20, [10, 10, 10, 10], Color::rgb(1, 2, 3)));

    expect($big)->toEqual($half)
        // the top edge is flat only between the two clamped corners
        ->and(contourBox($big[0][1][0]))->toEqual([0.0, 0.0, 40.0, 20.0]);
});

it('draws nothing for an empty rect', function () {
    expect((new RecordingEngine())->commandsOf(fn ($e) => $e->roundRect(0, 0, 0, 10, [4, 4, 4, 4], Color::rgb(1, 2, 3))))->toBe([]);
});

it('draws a shadow as 4 translucent fills, largest first', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($e) => $e->shadow(20, 20, 40, 30, 4, 8, Color::rgba(0, 0, 0, 0.5)));

    expect($commands)->toHaveCount(4);
    $widths = array_map(fn ($c) => contourBox($c[1][0])[2] - contourBox($c[1][0])[0], $commands);
    expect($widths[0])->toBeGreaterThan($widths[1])
        ->and($widths[1])->toBeGreaterThan($widths[2])
        ->and($widths[2])->toBeGreaterThan($widths[3]);
    foreach ($commands as $command) {
        expect($command[3] & 0xFF)->toBeGreaterThan(0)->toBeLessThan(128);
    }
});

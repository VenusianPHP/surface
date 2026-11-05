<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Framebuffers\Region;
use Surface\NutsAndBolts\Color;

/*
 * coverage() in the base class: an 8-bit mask in a colour, grouped into 16 alpha levels, one full-coverage
 * span command per level present, so every engine can draw it.
 */

it('records one span command per alpha level present and none for 0', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($g) => $g->coverage("\x00\x80\xFF", 3, 1, 10, 20, Color::rgb(255, 255, 255)));

    expect($commands)->toHaveCount(2)
        ->and(array_column($commands, 0))->toBe(['spans', 'spans'])
        ->and(unpack('vy/vx/vlength/Ccoverage', $commands[0][1]))->toBe(['y' => 20, 'x' => 11, 'length' => 1, 'coverage' => 255])
        ->and($commands[0][2] & 0xFF)->toBe(136)
        ->and(unpack('vy/vx/vlength/Ccoverage', $commands[1][1]))->toBe(['y' => 20, 'x' => 12, 'length' => 1, 'coverage' => 255])
        ->and($commands[1][2] & 0xFF)->toBe(255);
});

it('multiplies the colour alpha into each level', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($g) => $g->coverage("\xFF", 1, 1, 0, 0, Color::rgba(255, 255, 255, 0.5)));

    expect($commands[0][2] & 0xFF)->toBe(128);
});

it('rounds the origin to whole pixels through the transform and keeps to the clip', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($g) => $g->translate(0.6, 0.4)->clip(new Region(0, 0, 2, 10))->coverage("\xFF\xFF\xFF", 3, 1, 0, 0, Color::rgb(1, 2, 3)));

    expect(unpack('vy/vx/vlength/Ccoverage', $commands[0][1]))->toBe(['y' => 0, 'x' => 1, 'length' => 1, 'coverage' => 255]);
});

it('refuses a mask of the wrong size', function () {
    (new RecordingEngine())->frame(fn ($g) => $g->coverage("\xFF", 2, 1, 0, 0, Color::rgb(1, 2, 3)));
})->throws(DrawingException::class);

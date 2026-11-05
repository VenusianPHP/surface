<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Framebuffers\Region;
use Surface\NutsAndBolts\Color;

/*
 * pushClip() intersects with the clip in force; popClip() puts the one before back. clip() still replaces.
 */

it('intersects nested clips and restores them in order', function () {
    $seen = [];
    (new RecordingEngine())->frame(function ($g) use (&$seen): void {
        $g->pushClip(new Region(10, 10, 50, 50));
        $seen[] = $g->clipRegion();
        $g->pushClip(new Region(30, 0, 50, 40));
        $seen[] = $g->clipRegion();
        $g->popClip();
        $seen[] = $g->clipRegion();
        $g->popClip();
        $seen[] = $g->clipRegion();
    });

    expect($seen)->toEqual([new Region(10, 10, 50, 50), new Region(30, 10, 30, 30), new Region(10, 10, 50, 50), new Region(0, 0, 100, 80)]);
});

it('draws inside the intersection', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($g) => $g->pushClip(new Region(10, 10, 50, 50))->pushClip(new Region(30, 0, 50, 40))->fillRect(0, 0, 100, 80, Color::rgb(1, 2, 3)));

    expect($commands[0][4])->toEqual(new Region(30, 10, 30, 30));
});

it('keeps an empty intersection empty and draws nothing in it', function () {
    $commands = (new RecordingEngine())->commandsOf(fn ($g) => $g->pushClip(new Region(0, 0, 10, 10))->pushClip(new Region(50, 50, 10, 10))->fillRect(0, 0, 100, 80, Color::rgb(1, 2, 3)));

    expect($commands)->toBe([]);
});

it('refuses a popClip without a pushClip', function () {
    (new RecordingEngine())->frame(fn ($g) => $g->popClip());
})->throws(DrawingException::class);

it('leaves clip() replacing the clip as before', function () {
    $seen = null;
    (new RecordingEngine())->frame(function ($g) use (&$seen): void {
        $g->pushClip(new Region(10, 10, 20, 20))->clip(new Region(50, 50, 10, 10));
        $seen = $g->clipRegion();
    });

    expect($seen)->toEqual(new Region(50, 50, 10, 10));
});

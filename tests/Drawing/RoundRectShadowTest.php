<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\FillRule;
use Surface\NutsAndBolts\Color;

/* roundRect() and shadow() record roundrect, roundring and shadow commands under an upright, even transform. */

const RR_FILL = 0x336699FF;
const RR_EDGE = 0xFFCC00FF;

function rrFill(): Color
{
    return Color::hex('#336699');
}

function rrEdge(): Color
{
    return Color::hex('#ffcc00');
}

it('records roundrect and roundring for a bordered rect', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->roundRect(10, 10, 40, 20, [4, 4, 4, 50], rrFill(), rrEdge(), 2));
    $clip = new Region(0, 0, 100, 80);

    expect($commands)->toEqual([
        ['roundrect', 12.0, 12.0, 48.0, 28.0, [2.0, 2.0, 2.0, 8.0], RR_FILL, $clip],
        ['roundring', 10.0, 10.0, 50.0, 30.0, [4.0, 4.0, 4.0, 10.0], 2.0, RR_EDGE, $clip],
    ]);
});

it('records one roundrect without a border', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->roundRect(10, 10, 40, 20, [-1, 4, 4, 4], rrFill()));

    expect($commands)->toHaveCount(1)
        ->and($commands[0][0])->toBe('roundrect')
        ->and($commands[0][5])->toBe([0.0, 4.0, 4.0, 4.0]);
});

it('scales radii, border and blur with an even transform', function () {
    $commands = (new RecordingEngine)->commandsOf(function (RenderingEngine $g) {
        $g->scale(2)->roundRect(1, 1, 10, 10, [2, 2, 2, 2], rrFill(), rrEdge(), 1);
        $g->shadow(1, 1, 10, 10, 3, 4, rrFill());
    });

    expect(array_map(fn ($c) => array_slice($c, 0, -2), $commands))->toEqual([
        ['roundrect', 4.0, 4.0, 20.0, 20.0, [2.0, 2.0, 2.0, 2.0]],
        ['roundring', 2.0, 2.0, 22.0, 22.0, [4.0, 4.0, 4.0, 4.0], 2.0],
        ['shadow', 2.0, 4.0, 22.0, 24.0, 6.0, 8.0],
    ]);
});

it('draws a turned rounded rect as paths', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->translate(50, 40)->rotate(0.1)->roundRect(-20, -10, 40, 20, [6, 0, 6, 0], rrFill(), rrEdge(), 2));

    expect(array_column($commands, 0))->toBe(['path', 'path'])
        ->and($commands[0][2])->toBe(FillRule::NON_ZERO)
        ->and($commands[0][1])->toHaveCount(1)
        ->and($commands[1][2])->toBe(FillRule::EVEN_ODD)
        ->and($commands[1][1])->toHaveCount(2)
        ->and($commands[0][3])->toBe(RR_FILL)
        ->and($commands[1][3])->toBe(RR_EDGE);
});

it('refuses a turned shadow', function (Closure $turn) {
    (new RecordingEngine)->commandsOf(function (RenderingEngine $g) use ($turn) {
        $turn($g);
        $g->shadow(0, 0, 10, 10, 2, 4, rrFill());
    });
})->with([
    'a turn' => [fn (RenderingEngine $g) => $g->rotate(0.1)],
    'an uneven scale' => [fn (RenderingEngine $g) => $g->scale(2, 1)],
])->throws(DrawingException::class);

it('moves a shadow blur / 4 down', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->shadow(10, 10, 20, 20, 4, 8, rrFill()));

    expect($commands)->toEqual([['shadow', 10.0, 12.0, 30.0, 32.0, 4.0, 8.0, RR_FILL, new Region(0, 0, 100, 80)]]);
});

it('fills only the border when it reaches the middle', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->roundRect(0, 0, 20, 10, [2, 2, 2, 2], rrFill(), rrEdge(), 5));

    expect(array_column($commands, 0))->toBe(['roundring']);
});

it('damages nothing when the same rounded rect is drawn again', function () {
    $engine = new RecordingEngine(framebuffers()->driver('native')->dirty(FormatSpec::rgba8(), 64, 32));
    $still = fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))
        ->roundRect(4, 4, 30, 16, [4, 4, 4, 4], rrFill(), rrEdge(), 1)
        ->shadow(40, 4, 16, 16, 3, 6, rrFill());
    $engine->frame($still);
    $engine->frame($still);

    expect($engine->damage())->toBe([]);
});

it('boxes a shadow by its blur', function () {
    $engine = new RecordingEngine(framebuffers()->driver('native')->dirty(FormatSpec::rgba8(), 64, 64));
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0)));
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->shadow(20, 20, 10, 10, 2, 4, rrFill()));

    // the box 20..30 moved 1 down, grown by blur + 1
    expect($engine->damage())->toHaveCount(1)
        ->and($engine->damage()[0]->intersect(new Region(15, 16, 20, 20)))->toEqual(new Region(15, 16, 20, 20));
});

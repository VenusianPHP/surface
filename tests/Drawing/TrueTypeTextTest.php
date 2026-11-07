<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Fonts\TrueTypeFont;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Fonts\ClassicFont;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Fakes\FakeFontDriver;

/*
 * TrueType text records one 'glyphs' command (upright, even scale) or one path (anything else).
 * FakeFontDriver: one glyph per byte, advance size/2, ascent size*0.75, line height size; a mask is
 * ceil(size/2) x ceil(size*0.75) with top = height.
 */

function ttFont(float $size = 13.0): TrueTypeFont
{
    return new TrueTypeFont('sans-serif', $size, new FakeFontDriver);
}

function ttWhite(): Color
{
    return Color::rgb(255, 255, 255);
}

it('records one glyphs command for a TrueType text', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->text('Hi', 10, 20, ttWhite(), ttFont()));

    expect($commands)->toHaveCount(1)
        ->and($commands[0][0])->toBe('glyphs')
        ->and($commands[0][2])->toBe(13.0)
        ->and($commands[0][3])->toBe([['fake#0', 72, 10, 20, 0], ['fake#0', 105, 17, 20, 0]]);
});

it('scales the pixel size with an even transform', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->scale(2)->text('Hi', 1, 1, ttWhite(), ttFont()));

    expect($commands[0][0])->toBe('glyphs')->and($commands[0][2])->toBe(26.0);
});

it('draws TrueType text under a turn or uneven scale as one path', function (Closure $turn) {
    $driver = new FakeFontDriver;
    $font = new TrueTypeFont('sans-serif', 13.0, $driver);
    $engine = new RecordingEngine;
    $commands = $engine->commandsOf(function (RenderingEngine $g) use ($turn, $font) {
        $turn($g);
        $g->text('Hi', 10, 20, ttWhite(), $font);
    });

    // the fake outline is the mask box, top-left at the origin's (0, -height); origins at baseline 20 + 9.75
    $expected = [];
    foreach ([[10.0, 'H'], [16.5, 'i']] as [$ox, $char]) {
        $c = $driver->outline('fake#0', 13.0, ord($char), 0)[0];
        $expected[] = array_map(fn ($p) => [$ox + $p[0], 29.75 + $p[1]], $c);
    }
    $g = new RecordingEngine;
    $g->begin();
    $turn($g);
    $matrix = $g->matrix();
    $g->end();
    $expected = array_map(fn ($c) => array_map(fn ($p) => $matrix->apply($p[0], $p[1]), $c), $expected);

    expect($commands)->toHaveCount(1)
        ->and($commands[0][0])->toBe('path')
        ->and($commands[0][2])->toBe(FillRule::NON_ZERO)
        ->and($commands[0][1])->toEqualWithDelta($expected, 1e-9);
})->with([
    'a turn' => [fn (RenderingEngine $g) => $g->rotate(0.1)],
    'an uneven scale' => [fn (RenderingEngine $g) => $g->scale(2, 1)],
]);

it('starts each line one line height lower', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->text("A\nB", 0, 0, ttWhite(), ttFont()));
    $glyphs = $commands[0][3];

    expect($glyphs)->toHaveCount(2)->and($glyphs[1][3])->toBe($glyphs[0][3] + 13);
});

it('records nothing for empty text or only line breaks', function (string $text) {
    $engine = new RecordingEngine;

    expect($engine->commandsOf(fn (RenderingEngine $g) => $g->text($text, 0, 0, ttWhite(), ttFont())))->toBe([])
        ->and($engine->textBounds($text, ttFont()))->toBe([0.0, 0.0, 0.0, 0.0]);
})->with(['empty' => '', 'newline' => "\n", 'return' => "\r"]);

it('damages nothing when the same text is drawn again', function () {
    $engine = new RecordingEngine(framebuffers()->driver('native')->dirty(FormatSpec::rgba8(), 64, 32));
    $font = ttFont();
    $still = fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('Hi', 2, 2, ttWhite(), $font);
    $engine->frame($still);
    $engine->frame($still);

    expect($engine->damage())->toBe([]);
});

it('still draws bitmap text as before', function () {
    $commands = (new RecordingEngine)->commandsOf(fn (RenderingEngine $g) => $g->text('A', 0, 0, ttWhite(), new ClassicFont));

    expect($commands[0][0])->toBe('spans');
});

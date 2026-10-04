<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Framebuffer;
use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;
use Surface\Drawing\Text\Typesetter;
use Surface\Drawing\Velvet\VelvetGE;
use Surface\Fonts\ClassicFont;
use Surface\NutsAndBolts\Color;
use Venusian\Surface\Tests\Support\Fakes\TinyAAFace;
use Venusian\Surface\Tests\Support\Fakes\TinyFace;
use Venusian\Surface\Tests\Support\Framebuffers\Formats;

/*
 * Text on a rendering engine: a face's glyph bits become pixels of the
 * framebuffer, with no window anywhere. TinyFace is A, B, C:
 *
 *     A ###   B .#.   C ##    (C sits one row lower)
 *       #.#     ###     #.
 *       ###     .#.     ##
 */

/** The framebuffer as rows of '#' (exactly the ink colour) and '.' (anything else). */
function inked(Framebuffer $buffer, int $ink = 0xFFFFFFFF): array
{
    $rows = [];
    for ($y = 0; $y < $buffer->viewportHeight(); $y++) {
        $row = '';
        for ($x = 0; $x < $buffer->viewportWidth(); $x++) {
            $row .= $buffer->getPixel($x, $y) === $ink ? '#' : '.';
        }
        $rows[] = $row;
    }

    return $rows;
}

function textEngine(RasterizeDriver $raster, FramebufferDriver $framebuffers, int $width, int $height, Edges $edges = Edges::ANTIALIASED): VelvetGE
{
    return new VelvetGE($framebuffers->full(FormatSpec::rgba8(), $width, $height), $raster, $edges);
}

it('draws each glyph pixel as one framebuffer pixel, the first line box at the origin given', function (RasterizeDriver $raster, FramebufferDriver $framebuffers, Edges $edges): void {
    $engine = textEngine($raster, $framebuffers, 12, 6, $edges);

    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('ABC', 1, 1, Color::rgb(255, 255, 255), new TinyFace));

    expect(inked($engine->framebuffer()))->toBe([
        '............',
        '.###..#.....',
        '.#.#.###.##.',
        '.###..#..#..',
        '.........##.',
        '............',
    ]);
})->with('engine pairings')->with(['anti-aliased' => [Edges::ANTIALIASED], 'hard' => [Edges::HARD]]);

it('starts a new line on a line feed, ignores a carriage return, and skips codes the face lacks', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = textEngine($raster, $framebuffers, 8, 12);

    // TinyFace's line height is 8. 'z' and '?' are not in it and take no room.
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text("zA\r\n?B", 0, 0, Color::rgb(255, 255, 255), new TinyFace));

    expect(inked($engine->framebuffer()))->toBe([
        '###.....',
        '#.#.....',
        '###.....',
        '........',
        '........',
        '........',
        '........',
        '........',
        '.#......',
        '###.....',
        '.#......',
        '........',
    ]);
})->with('engine pairings');

it('draws the classic face exactly as its column bytes say', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $face = new ClassicFont;
    $glyph = $face->glyph(ord('R'));
    $coverage = (new Typesetter)->coverage($face, $glyph);
    $engine = textEngine($raster, $framebuffers, $glyph->width + 2, $glyph->height + 2);

    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('R', 1, 1, Color::rgb(255, 255, 255), $face));

    $expected = [str_repeat('.', $glyph->width + 2)];
    for ($row = 0; $row < $glyph->height; $row++) {
        $line = '.';
        for ($column = 0; $column < $glyph->width; $column++) {
            $line .= $coverage[$row * $glyph->width + $column] === "\xff" ? '#' : '.';
        }
        $expected[] = $line.'.';
    }
    $expected[] = str_repeat('.', $glyph->width + 2);

    expect(inked($engine->framebuffer()))->toBe($expected)
        ->and(substr_count(implode('', $expected), '#'))->toBeGreaterThan(10);
})->with('engine pairings');

it('scales through the transform: one glyph pixel becomes a block', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = textEngine($raster, $framebuffers, 8, 8);

    $engine->frame(function (RenderingEngine $g): void {
        $g->clear(Color::rgb(0, 0, 0));
        $g->translate(1, 1)->scale(2);
        $g->text('A', 0, 0, Color::rgb(255, 255, 255), new TinyFace);
    });

    expect(inked($engine->framebuffer()))->toBe([
        '........',
        '.######.',
        '.######.',
        '.##..##.',
        '.##..##.',
        '.######.',
        '.######.',
        '........',
    ]);
})->with('engine pairings');

it('turns through the transform: a quarter turn stands the text on its side', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = textEngine($raster, $framebuffers, 5, 5, Edges::HARD);

    // C is ##/#./## one row down; turned a quarter clockwise about (4, 0) its rows become columns, right to left.
    $engine->frame(function (RenderingEngine $g): void {
        $g->clear(Color::rgb(0, 0, 0));
        $g->translate(4, 0)->rotate(M_PI / 2);
        $g->text('C', 0, 0, Color::rgb(255, 255, 255), new TinyFace);
    });

    expect(inked($engine->framebuffer()))->toBe([
        '###..',
        '#.#..',
        '.....',
        '.....',
        '.....',
    ]);
})->with('engine pairings');

it('keeps text inside the clip', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = textEngine($raster, $framebuffers, 8, 4);

    $engine->frame(function (RenderingEngine $g): void {
        $g->clear(Color::rgb(0, 0, 0));
        $g->clip(new Region(0, 0, 5, 2));
        $g->text('AB', 0, 0, Color::rgb(255, 255, 255), new TinyFace);
    });

    // Unclipped: ###..#.. / #.#.###. / ###..#..; the clip keeps columns 0..4 of rows 0..1.
    expect(inked($engine->framebuffer()))->toBe([
        '###.....',
        '#.#.#...',
        '........',
        '........',
    ]);
})->with('engine pairings');

it('blends the colour given, alpha included', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = textEngine($raster, $framebuffers, 3, 3);

    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('B', 0, 0, Color::rgba(255, 255, 255, 0.5), new TinyFace));

    expect($engine->framebuffer()->getPixel(1, 1))->toBe(0x808080FF)
        ->and($engine->framebuffer()->getPixel(0, 0))->toBe(0x000000FF);
})->with('engine pairings');

it('draws a 4-bit face where its nibbles reach the face\'s threshold', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = textEngine($raster, $framebuffers, 2, 8);

    // Nibbles F 3 / 9 0 against the default threshold 8: F and 9 ink.
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('A', 0, 0, Color::rgb(255, 255, 255), new TinyAAFace));

    expect(array_values(array_filter(inked($engine->framebuffer()), fn (string $row): bool => $row !== '..')))->toBe(['#.', '#.']);
})->with('engine pairings');

it('draws nothing, and records nothing, for text with no ink', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = textEngine($raster, $framebuffers, 4, 4);

    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text("?? \n", 0, 0, Color::rgb(255, 255, 255), new TinyFace));

    expect(implode('', inked($engine->framebuffer())))->toBe(str_repeat('.', 16));
})->with('engine pairings');

it('draws on a one-bit panel format', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = new VelvetGE($framebuffers->full(Formats::mono(), 8, 3), $raster);

    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('AB', 0, 0, Color::rgb(255, 255, 255), new TinyFace));

    // One bit a pixel, most significant first: ###..#.. / #.#.###. / ###..#..
    expect(bin2hex($engine->framebuffer()->dump()))->toBe('e4'.'ae'.'e4');
})->with('engine pairings');

it('replays text with the rest of the frame', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $engine = textEngine($raster, $framebuffers, 4, 4);
    $engine->frame(fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text('A', 0, 0, Color::rgb(255, 255, 255), new TinyFace));
    $first = $engine->framebuffer()->dump();

    $engine->framebuffer()->fill(0);
    $engine->replay();

    expect(bin2hex($engine->framebuffer()->dump()))->toBe(bin2hex($first));
})->with('engine pairings');

it('measures the ink box of a text without drawing', function (): void {
    $engine = new VelvetGE((new Surface\Framebuffers\Native\NativeFramebufferDriver)->full(FormatSpec::rgba8(), 4, 4), new Surface\Rasterize\Native\NativeRasterizeDriver);

    expect($engine->textBounds('ABC', new TinyFace))->toBe([0.0, 0.0, 10.0, 4.0])
        ->and($engine->textBounds("A\nB", new TinyFace))->toBe([0.0, 0.0, 3.0, 11.0])
        ->and($engine->textBounds('C', new TinyFace))->toBe([0.0, 1.0, 2.0, 3.0])
        ->and($engine->textBounds('??', new TinyFace))->toBe([0.0, 0.0, 0.0, 0.0]);
});

it('refuses text outside a frame, and a position that is not a number', function (): void {
    $engine = new VelvetGE((new Surface\Framebuffers\Native\NativeFramebufferDriver)->full(FormatSpec::rgba8(), 4, 4), new Surface\Rasterize\Native\NativeRasterizeDriver);

    expect(fn () => $engine->text('A', 0, 0, Color::rgb(255, 255, 255), new TinyFace))->toThrow(DrawingException::class, 'text() belongs inside a frame')
        ->and(fn () => $engine->frame(fn (RenderingEngine $g) => $g->text('A', NAN, 0, Color::rgb(255, 255, 255), new TinyFace)))->toThrow(DrawingException::class);
});

it('gives the same pixels whether the text lands on whole pixels or goes through the rasteriser', function (RasterizeDriver $raster, FramebufferDriver $framebuffers, int $scale): void {
    $text = "The quick\nbrown fox\njumps over";
    $draw = function (bool $turned) use ($raster, $framebuffers, $text, $scale): string {
        $engine = textEngine($raster, $framebuffers, 140, 70);
        $engine->frame(function (RenderingEngine $g) use ($text, $scale, $turned): void {
            $g->clear(Color::rgb(0, 0, 40));
            $g->clip(new Region(3, 2, 120, 55));
            // A full turn leaves the picture alone and the matrix a hair off upright, which sends the text through the rasteriser.
            $turned ? $g->rotate(2 * M_PI) : $g;
            $g->translate(4, 3)->scale($scale);
            $g->text($text, 1, 1, Color::rgb(255, 200, 0), new ClassicFont);
        });

        return $engine->framebuffer()->dump();
    };

    expect(bin2hex($draw(false)))->toBe(bin2hex($draw(true)))
        ->and(substr_count($draw(false), "\xff\xc8\x00\xff"))->toBeGreaterThan(200);
})->with('engine pairings')->with(['at 1x' => [1], 'at 2x' => [2]]);

it('draws whole-pixel text on a paged framebuffer one page at a time, as on a full one', function (RasterizeDriver $raster, FramebufferDriver $framebuffers): void {
    $draw = fn (RenderingEngine $g) => $g->clear(Color::rgb(0, 0, 0))->text("AB\nCA", 1, 1, Color::rgb(255, 255, 255), new TinyFace);
    $full = textEngine($raster, $framebuffers, 10, 14);
    $full->frame($draw);

    $paged = $framebuffers->paged(FormatSpec::rgba8(), 10, 14, 4);
    $engine = new VelvetGE($paged, $raster);
    $rows = '';
    for ($page = 0; $page < $paged->pages(); $page++) {
        $paged->setPage($page);
        $page === 0 ? $engine->frame($draw) : $engine->replay();
        $rows .= $paged->dump();
    }

    expect(bin2hex(substr($rows, 0, 10 * 14 * 4)))->toBe(bin2hex($full->framebuffer()->dump()));
})->with('engine pairings');

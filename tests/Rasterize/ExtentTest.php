<?php

declare(strict_types=1);

use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\Edges;
use Surface\Contracts\Rasterize\RasterizeDriver;

/*
 * A scanner walks only the rows and columns a shape reaches, not its whole clip: a shape in a large clip answers the
 * spans it answers in a clip that only just holds it, and a shape cut by its clip the spans of the part inside.
 */

/** @return array<string, Closure(Surface\Contracts\Rasterize\Rasterizer): string> */
function extentShapes(float $x, float $y): array
{
    return [
        'path' => fn ($r) => $r->fillPath([[[$x + 0.3, $y + 1.2], [$x + 17.7, $y + 3.4], [$x + 6.1, $y + 13.9]]]),
        'ellipse' => fn ($r) => $r->fillEllipse($x + 9.25, $y + 7.5, 8.6, 6.3),
        'ring' => fn ($r) => $r->strokeEllipse($x + 9.25, $y + 7.5, 8.6, 6.3, 2.5),
        'rounded rect' => fn ($r) => $r->fillRoundRect($x + 0.4, $y + 0.6, 18.2, 13.1, [4.0, 0.0, 6.5, 2.25]),
        'rounded ring' => fn ($r) => $r->strokeRoundRect($x + 0.4, $y + 0.6, 18.2, 13.1, [4.0, 0.0, 6.5, 2.25], 1.75),
    ];
}

it('answers the spans a tight clip gives, in a clip many times larger', function (RasterizeDriver $driver, Edges $edges): void {
    foreach (extentShapes(500.0, 700.0) as $name => $draw) {
        $tight = $draw($driver->rasterizer(new Region(497, 697, 26, 22), $edges));
        $large = $draw($driver->rasterizer(new Region(0, 0, 4000, 3000), $edges));

        expect($tight)->not->toBe('', $name)
            ->and(bin2hex($large))->toBe(bin2hex($tight), $name);
    }
})->with('rasterize drivers')->with([Edges::ANTIALIASED, Edges::HARD]);

it('answers only the part inside when the clip cuts the shape, on any side', function (RasterizeDriver $driver, Edges $edges): void {
    foreach (extentShapes(500.0, 700.0) as $name => $draw) {
        $whole = $draw($driver->rasterizer(new Region(0, 0, 4000, 3000), $edges));
        foreach ([new Region(505, 690, 40, 40), new Region(480, 705, 25, 30), new Region(490, 690, 40, 8)] as $clip) {
            $kept = '';
            foreach (str_split($whole, 7) as $span) {
                [$sy, $sx, $length, $coverage] = array_values(unpack('vy/vx/vlength/Ccoverage', $span));
                $from = max($sx, $clip->x);
                $to = min($sx + $length, $clip->right());
                if ($sy >= $clip->y && $sy < $clip->bottom() && $to > $from) {
                    $kept .= pack('vvvC', $sy, $from, $to - $from, $coverage);
                }
            }

            expect(bin2hex($draw($driver->rasterizer($clip, $edges))))->toBe(bin2hex($kept), "{$name} in {$clip->x},{$clip->y} {$clip->width}x{$clip->height}");
        }
    }
})->with('rasterize drivers')->with([Edges::ANTIALIASED, Edges::HARD]);

it('answers nothing for a shape wholly outside its clip', function (RasterizeDriver $driver): void {
    foreach (extentShapes(500.0, 700.0) as $name => $draw) {
        expect($draw($driver->rasterizer(new Region(0, 0, 400, 300), Edges::ANTIALIASED)))->toBe('', $name);
    }
})->with('rasterize drivers');

<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\DrawingException;
use Surface\Contracts\Drawing\RenderingEngine;
use Surface\Contracts\Framebuffers\Filter;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\Region;
use Surface\Contracts\Rasterize\FillRule;
use Surface\Framebuffers\Native\NativeFullFramebuffer;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;

/*
 * What every rendering engine shares: frames, the transform and clip state,
 * and the lowering of each draw call to a command in framebuffer pixels.
 * RecordingEngine draws nothing and keeps the commands it was handed.
 */

function white(): Color
{
    return Color::rgb(255, 255, 255);
}

function surface(): Region
{
    return new Region(0, 0, 100, 80);
}

it('runs a frame: begin, the draw calls, end, one execution', function (): void {
    $engine = new RecordingEngine();

    expect($engine->drawing())->toBeFalse();
    $returned = $engine->frame(function (RenderingEngine $g) use ($engine): void {
        expect($g)->toBe($engine)
            ->and($g->drawing())->toBeTrue()
            ->and($engine->executed)->toBe([]);   // nothing is drawn until the frame ends
        $g->clear(white());
    });

    expect($returned)->toBe($engine)
        ->and($engine->drawing())->toBeFalse()
        ->and($engine->executed)->toBe([[['clear', 0xFFFFFFFF]]])
        ->and($engine->name())->toBe('recording')
        ->and([$engine->width(), $engine->height()])->toBe([100, 80]);
});

it('takes begin() and end() by hand as well', function (): void {
    $engine = new RecordingEngine();
    $engine->begin()->clear(white())->end();

    expect($engine->executed)->toHaveCount(1)
        ->and(fn () => $engine->end())->toThrow(DrawingException::class, 'end() belongs inside a frame')
        ->and(fn () => $engine->begin()->begin())->toThrow(DrawingException::class, 'A frame is already open');
});

it('refuses draw and state calls outside a frame', function (Closure $call, string $name): void {
    expect(fn () => $call(new RecordingEngine()))->toThrow(DrawingException::class, "{$name}() belongs inside a frame");
})->with([
    [fn (RenderingEngine $g) => $g->clear(white()), 'clear'],
    [fn (RenderingEngine $g) => $g->fillRect(0, 0, 1, 1, white()), 'fillRect'],
    [fn (RenderingEngine $g) => $g->image(new NativeFullFramebuffer(FormatSpec::rgba8(), 1, 1), 0, 0), 'image'],
    [fn (RenderingEngine $g) => $g->translate(1, 1), 'translate'],
    [fn (RenderingEngine $g) => $g->push(), 'push'],
    [fn (RenderingEngine $g) => $g->clip(null), 'clip'],
]);

it('replays the last ended frame, and only that', function (): void {
    $engine = new RecordingEngine();

    expect(fn () => $engine->replay())->toThrow(DrawingException::class, 'There is no frame to replay');

    $engine->frame(fn (RenderingEngine $g) => $g->clear(white()));
    $engine->replay()->replay();

    expect($engine->executed)->toHaveCount(3)
        ->and($engine->executed[2])->toBe($engine->executed[0])
        ->and(fn () => $engine->frame(fn (RenderingEngine $g) => $g->replay()))->toThrow(DrawingException::class, 'replay() comes after end()');
});

it('drops a frame whose draw throws: nothing is drawn and the last frame stays', function (): void {
    $engine = new RecordingEngine();
    $engine->frame(fn (RenderingEngine $g) => $g->clear(white()));

    expect(fn () => $engine->frame(function (RenderingEngine $g): void {
        $g->fillRect(0, 0, 5, 5, white());
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom')
        ->and($engine->drawing())->toBeFalse()
        ->and($engine->executed)->toHaveCount(1);

    $engine->replay();
    expect($engine->executed[1])->toBe([['clear', 0xFFFFFFFF]]);
});

it('starts every frame with the identity transform and no clip', function (): void {
    $engine = new RecordingEngine();
    $engine->frame(fn (RenderingEngine $g) => $g->translate(5, 5)->clip(new Region(1, 1, 2, 2))->push());

    $engine->frame(function (RenderingEngine $g): void {
        expect($g->matrix())->toEqual(Affine::identity())
            ->and($g->clipRegion())->toEqual(surface())
            ->and(fn () => $g->pop())->toThrow(DrawingException::class, 'pop() without a push()');
    });

    expect($engine->matrix())->toEqual(Affine::identity());
});

it('turns a colour into 0xRRGGBBAA, each channel rounded', function (): void {
    $commands = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->clear(Color::rgba(255, 128, 0, 0.5))->clear(new Color(0.5, 0.499, 0.0, 1.0)));

    expect($commands)->toBe([['clear', 0xFF800080], ['clear', 0x807F00FF]]);
});

it('lowers a rect to its four corners, through the transform, in call order', function (): void {
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g): void {
        $g->fillRect(1, 2, 3, 4, white());
        $g->translate(10, 0)->scale(2);
        $g->fillRect(1, 1, 1, 1, white());
    });

    expect($commands)->toEqual([
        ['path', [[[1.0, 2.0], [4.0, 2.0], [4.0, 6.0], [1.0, 6.0]]], FillRule::NON_ZERO, 0xFFFFFFFF, surface()],
        ['path', [[[12.0, 2.0], [14.0, 2.0], [14.0, 4.0], [12.0, 4.0]]], FillRule::NON_ZERO, 0xFFFFFFFF, surface()],
    ]);
});

it('composes transforms like a canvas: the last one called is the first a point goes through', function (): void {
    $engine = new RecordingEngine();
    $engine->frame(function (RenderingEngine $g): void {
        $g->scale(2, 3)->translate(10, 10);
        expect($g->matrix()->apply(1.0, 1.0))->toBe([22.0, 33.0]);

        $g->transform(new Affine(0.0, 1.0, -1.0, 0.0, 0.0, 0.0));        // a quarter turn, exactly
        expect($g->matrix()->apply(1.0, 0.0))->toBe([20.0, 33.0]);
    });
});

it('restores the transform and the clip on pop', function (): void {
    (new RecordingEngine())->frame(function (RenderingEngine $g): void {
        $g->translate(5, 5)->clip(new Region(10, 10, 20, 20));
        $g->push()->rotate(1.0)->scale(3)->clip(null);
        $g->push()->translate(1, 1);
        $g->pop()->pop();

        expect($g->matrix())->toEqual(Affine::translation(5.0, 5.0))
            ->and($g->clipRegion())->toEqual(new Region(10, 10, 20, 20));
    });
});

it('keeps the clip in framebuffer pixels, inside the surface, whatever the transform', function (): void {
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g): void {
        $g->scale(10)->clip(new Region(90, 70, 50, 50));
        expect($g->clipRegion())->toEqual(new Region(90, 70, 10, 10));
        $g->fillRect(0, 0, 1, 1, white());

        $g->clip(new Region(200, 200, 5, 5));                               // nothing of it is on the surface
        expect($g->clipRegion()->isEmpty())->toBeTrue();
        $g->fillRect(0, 0, 1, 1, white())->line(0, 0, 5, 5, white())->fillEllipse(5, 5, 2, 2, white());
        $g->clear(white());                                                 // clear ignores the clip
    });

    expect($commands)->toHaveCount(2)
        ->and($commands[0][4])->toEqual(new Region(90, 70, 10, 10))
        ->and($commands[1])->toBe(['clear', 0xFFFFFFFF]);
});

it('lowers lines, polylines and stroked rects to polylines, strokes scaled by the transform', function (): void {
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g): void {
        $g->line(0, 0, 4, 3, white());
        $g->scale(2, 8);                                                    // strokes grow by sqrt(2 × 8) = 4
        $g->polyline([[0, 0], [1, 1], [2, 0]], white(), 1.5, true);
        $g->strokeRect(1, 1, 2, 1, white(), 0.5);
    });

    expect($commands)->toEqual([
        ['polyline', [[0.0, 0.0], [4.0, 3.0]], 1.0, false, 0xFFFFFFFF, surface()],
        ['polyline', [[0.0, 0.0], [2.0, 8.0], [4.0, 0.0]], 6.0, true, 0xFFFFFFFF, surface()],
        ['polyline', [[2.0, 8.0], [6.0, 8.0], [6.0, 16.0], [2.0, 16.0]], 2.0, true, 0xFFFFFFFF, surface()],
    ]);
});

it('lowers triangles, polygons and paths to paths with their fill rule', function (): void {
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g): void {
        $g->translate(1, 1);
        $g->fillTriangle(0, 0, 4, 0, 0, 4, white());
        $g->fillPolygon([[0, 0], [4, 4], [4, 0], [0, 4]], white(), FillRule::EVEN_ODD);
        $g->fillPath([[[0, 0], [9, 0], [9, 9]], [[1, 1], [2, 1]], [[3, 3], [5, 3], [5, 5]]], white());
    });

    expect($commands)->toEqual([
        ['path', [[[1.0, 1.0], [5.0, 1.0], [1.0, 5.0]]], FillRule::NON_ZERO, 0xFFFFFFFF, surface()],
        ['path', [[[1.0, 1.0], [5.0, 5.0], [5.0, 1.0], [1.0, 5.0]]], FillRule::EVEN_ODD, 0xFFFFFFFF, surface()],
        ['path', [[[1.0, 1.0], [10.0, 1.0], [10.0, 10.0]], [[4.0, 4.0], [6.0, 4.0], [6.0, 6.0]]], FillRule::NON_ZERO, 0xFFFFFFFF, surface()],   // the two-point contour has no inside
    ]);
});

it('keeps an ellipse an ellipse while the transform keeps its axes, mirrored or not', function (): void {
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g): void {
        $g->translate(10, 20)->scale(-2, 3);
        $g->fillEllipse(1, 1, 4, 5, white());
        $g->strokeEllipse(1, 1, 4, 5, white(), 2);
    });

    expect($commands)->toEqual([
        ['ellipse', 8.0, 23.0, 8.0, 15.0, 0xFFFFFFFF, surface()],
        ['ring', 8.0, 23.0, 8.0, 15.0, 2 * sqrt(6), 0xFFFFFFFF, surface()],
    ]);
});

it('turns a rotated ellipse into a polygon that follows it within a tenth of a pixel', function (): void {
    $turn = Affine::translation(50.0, 40.0)->multiply(Affine::rotation(0.5));
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g) use ($turn): void {
        $g->transform($turn);
        $g->fillEllipse(0, 0, 30, 10, white());
        $g->strokeEllipse(0, 0, 30, 10, white(), 3);
    });

    [$fill, $stroke] = $commands;
    $back = $turn->inverse();
    $points = $fill[1][0];
    $count = count($points);

    expect($fill[0])->toBe('path')
        ->and($stroke[0])->toBe('polyline')
        ->and($stroke[1])->toEqual($points)
        ->and(abs($stroke[2] - 3.0))->toBeLessThan(1e-12)
        ->and($stroke[3])->toBeTrue()
        ->and($count)->toBeGreaterThanOrEqual(12);
    foreach ($points as $i => [$x, $y]) {
        [$u, $v] = $back->apply($x, $y);
        expect(abs(($u / 30) ** 2 + ($v / 10) ** 2 - 1.0))->toBeLessThan(1e-9, "point {$i} is on the ellipse");

        // the midpoint of each side is the furthest the polygon strays from the curve
        [$nx, $ny] = $points[($i + 1) % $count];
        [$mu, $mv] = $back->apply(($x + $nx) / 2, ($y + $ny) / 2);
        expect((1.0 - sqrt(($mu / 30) ** 2 + ($mv / 10) ** 2)) * 30)->toBeLessThan(0.1, "side {$i} stays close");
    }
});

it('places an image through the transform, at its own size or the one given', function (): void {
    $source = new NativeFullFramebuffer(FormatSpec::rgba8(), 4, 2);
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g) use ($source): void {
        $g->image($source, 3, 5);
        $g->translate(10, 10)->scale(2);
        $g->image($source, 1, 1, 8, 2, 0.5, Filter::LINEAR);
    });

    expect($commands)->toEqual([
        ['image', $source, Affine::translation(3.0, 5.0), 255, Filter::NEAREST, surface()],
        ['image', $source, new Affine(4.0, 0.0, 0.0, 2.0, 12.0, 12.0), 128, Filter::LINEAR, surface()],
    ])->and($commands[0][1])->toBe($source);
});

it('draws nothing for a zero or negative size, radius, stroke or opacity, or too few points', function (): void {
    $source = new NativeFullFramebuffer(FormatSpec::rgba8(), 4, 2);
    $commands = (new RecordingEngine())->commandsOf(function (RenderingEngine $g) use ($source): void {
        $g->fillRect(1, 1, 0, 5, white())->fillRect(1, 1, 5, -1, white());
        $g->strokeRect(1, 1, 5, 5, white(), 0)->strokeRect(1, 1, 0, 5, white());
        $g->line(0, 0, 5, 5, white(), -1)->polyline([], white())->polyline([[1, 1], [2, 2]], white(), 0);
        $g->fillPolygon([[1, 1], [2, 2]], white())->fillPath([], white())->fillPath([[[1, 1]]], white());
        $g->fillEllipse(5, 5, 0, 3, white())->strokeEllipse(5, 5, 3, -3, white())->strokeEllipse(5, 5, 3, 3, white(), 0);
        $g->image($source, 0, 0, 0, 2)->image($source, 0, 0, 4, -2)->image($source, 0, 0, opacity: 0.0)->image($source, 0, 0, opacity: 0.001);
        $g->push()->scale(0)->line(0, 0, 5, 5, white())->strokeEllipse(5, 5, 3, 3, white())->image($source, 0, 0)->pop();
    });

    expect($commands)->toBe([]);
});

it('refuses numbers that are not finite, or land past its limit once transformed', function (Closure $draw, string $message): void {
    $engine = new RecordingEngine();

    expect(fn () => $engine->frame($draw))->toThrow(DrawingException::class, $message)
        ->and($engine->executed)->toBe([]);
})->with([
    'a NAN coordinate' => [fn (RenderingEngine $g) => $g->fillRect(NAN, 0, 1, 1, white()), 'x is not a finite number'],
    'an infinite size' => [fn (RenderingEngine $g) => $g->fillRect(0, 0, INF, 1, white()), 'width is not a finite number'],
    'an infinite stroke' => [fn (RenderingEngine $g) => $g->line(0, 0, 1, 1, white(), INF), 'stroke is not a finite number'],
    'a NAN radius' => [fn (RenderingEngine $g) => $g->fillEllipse(0, 0, NAN, 1, white()), 'rx is not a finite number'],
    'a NAN angle' => [fn (RenderingEngine $g) => $g->rotate(NAN), 'radians is not a finite number'],
    'an infinite scale' => [fn (RenderingEngine $g) => $g->scale(INF), 'x is not a finite number'],
    'a point of a polygon' => [fn (RenderingEngine $g) => $g->fillPolygon([[0, 0], [1, NAN], [2, 2]], white()), 'Point 1 y is not a finite number'],
    'an opacity above 1' => [fn (RenderingEngine $g) => $g->image(new NativeFullFramebuffer(FormatSpec::rgba8(), 1, 1), 0, 0, opacity: 1.5), 'opacity is 0..1'],
    'a rect past the limit' => [fn (RenderingEngine $g) => $g->fillRect(0, 0, RenderingEngine::LIMIT * 2, 1, white()), 'is past ±16777216 once transformed'],
    'a point scaled past the limit' => [fn (RenderingEngine $g) => $g->scale(1e6)->line(0, 0, 100, 0, white()), 'is past ±16777216 once transformed'],
    'a stroke scaled past the limit' => [fn (RenderingEngine $g) => $g->scale(1e6)->line(0, 0, 1, 0, white(), 100), 'stroke 100000000 is past'],
    'a radius scaled past the limit' => [fn (RenderingEngine $g) => $g->scale(1e6)->fillEllipse(0, 0, 100, 1, white()), 'is past ±16777216 once transformed'],
]);

it('refuses points and contours that are not what they should be, naming them', function (Closure $draw, string $message): void {
    expect(fn () => (new RecordingEngine())->frame($draw))->toThrow(DrawingException::class, $message);
})->with([
    'too short' => [fn (RenderingEngine $g) => $g->fillPolygon([[0, 0], [1], [2, 2]], white()), 'Point 1 is not [x, y].'],
    'a string' => [fn (RenderingEngine $g) => $g->polyline([[0, 0], ['1', 1]], white()), 'Point 1 is not [x, y].'],
    'keyed' => [fn (RenderingEngine $g) => $g->polyline([[0, 0], ['x' => 1, 'y' => 1]], white()), 'Point 1 is not [x, y].'],
    'a contour that is no list' => [fn (RenderingEngine $g) => $g->fillPath([[[0, 0], [4, 0], [4, 4]], 'x'], white()), 'Contour 1 is not a list of points.'],
]);

it('takes the limit itself', function (): void {
    $commands = (new RecordingEngine())->commandsOf(fn (RenderingEngine $g) => $g->line(-RenderingEngine::LIMIT, 0, RenderingEngine::LIMIT, 0, white()));

    expect($commands)->toHaveCount(1);
});

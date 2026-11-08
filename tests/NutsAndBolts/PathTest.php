<?php

declare(strict_types=1);

use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Path;

/** The distance from a point to the nearest piece of a polyline. */
function nearest(array $points, float $x, float $y): float
{
    $best = INF;
    for ($i = 0; $i < count($points) - 1; $i++) {
        [$x0, $y0] = $points[$i];
        [$x1, $y1] = $points[$i + 1];
        $dx = $x1 - $x0;
        $dy = $y1 - $y0;
        $squared = $dx * $dx + $dy * $dy;
        $t = $squared > 0 ? max(0.0, min(1.0, (($x - $x0) * $dx + ($y - $y0) * $dy) / $squared)) : 0.0;
        $best = min($best, hypot($x - ($x0 + $t * $dx), $y - ($y0 + $t * $dy)));
    }

    return $best;
}

it('builds subpaths of lines and keeps the ops as given', function (): void {
    $path = (new Path())->moveTo(1, 2)->lineTo(3, 4)->quadTo(5, 6, 7, 8)->cubicTo(1, 1, 2, 2, 3, 3)->close();

    expect($path->ops())->toBe([['M', 1.0, 2.0], ['L', 3.0, 4.0], ['Q', 5.0, 6.0, 7.0, 8.0], ['C', 1.0, 1.0, 2.0, 2.0, 3.0, 3.0], ['Z']])
        ->and($path->isEmpty())->toBeFalse()
        ->and((new Path())->isEmpty())->toBeTrue();
});

it('starts a new subpath at the start of a closed one when a segment follows close()', function (): void {
    $path = (new Path())->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10)->close()->lineTo(0, 5);

    expect($path->ops())->toBe([['M', 0.0, 0.0], ['L', 10.0, 0.0], ['L', 10.0, 10.0], ['Z'], ['M', 0.0, 0.0], ['L', 0.0, 5.0]])
        ->and((new Path())->close()->isEmpty())->toBeTrue();
});

it('refuses a segment before moveTo() and numbers that are not finite', function (): void {
    expect(fn () => (new Path())->lineTo(1, 1))->toThrow(InvalidArgumentException::class, 'moveTo()')
        ->and(fn () => (new Path())->moveTo(NAN, 0))->toThrow(InvalidArgumentException::class, 'finite')
        ->and(fn () => (new Path())->moveTo(0, 0)->cubicTo(0, 0, INF, 0, 1, 1))->toThrow(InvalidArgumentException::class, 'finite');
});

it('flattens lines as they are, open or closed, and leaves out a lone moveTo', function (): void {
    $path = (new Path())->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10)->close()->moveTo(50, 50)->moveTo(20, 20)->lineTo(30, 20);

    expect($path->flatten())->toBe([
        ['points' => [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]], 'closed' => true],
        ['points' => [[20.0, 20.0], [30.0, 20.0]], 'closed' => false],
    ]);
});

it('flattens a cubic and a quadratic within the tolerance of the curve', function (float $tolerance): void {
    $cubic = (new Path())->moveTo(0, 0)->cubicTo(0, 100, 100, 100, 100, 0);
    $quad = (new Path())->moveTo(0, 0)->quadTo(50, 100, 100, 0);
    $cubicPoints = $cubic->flatten($tolerance)[0]['points'];
    $quadPoints = $quad->flatten($tolerance)[0]['points'];

    $worst = 0.0;
    for ($i = 0; $i <= 200; $i++) {
        $t = $i / 200;
        $u = 1 - $t;
        $worst = max($worst, nearest($cubicPoints, 3 * $u * $t * ($u * 0 + $t * 100) + $t ** 3 * 100, 3 * $u * $u * $t * 100 + 3 * $u * $t * $t * 100));
        $worst = max($worst, nearest($quadPoints, 2 * $u * $t * 50 + $t * $t * 100, 2 * $u * $t * 100));
    }

    expect($worst)->toBeLessThanOrEqual($tolerance)
        ->and($cubicPoints[0])->toBe([0.0, 0.0])
        ->and($cubicPoints[array_key_last($cubicPoints)])->toBe([100.0, 0.0]);
})->with([0.25, 0.05, 1.0]);

it('flattens the same path to the same points every time, finer for a smaller tolerance', function (): void {
    $path = (new Path())->moveTo(0, 0)->cubicTo(10, 80, 90, -40, 100, 30);

    expect($path->flatten(0.25))->toBe($path->flatten(0.25))
        ->and(count($path->flatten(0.01)[0]['points']))->toBeGreaterThan(count($path->flatten(0.5)[0]['points']));
});

it('does not cut a corner where a control point runs past the chord', function (): void {
    // the curve overshoots to the right of its end before it returns: a straight chord would miss that
    $path = (new Path())->moveTo(0, 0)->cubicTo(40, 0, 40, 0, 10, 0);
    $xs = array_column($path->flatten(0.25)[0]['points'], 0);

    expect(max($xs))->toBeGreaterThan(20.0);
});

it('refuses a tolerance that is not positive', function (): void {
    expect(fn () => (new Path())->moveTo(0, 0)->lineTo(1, 1)->flatten(0.0))->toThrow(InvalidArgumentException::class, 'tolerance')
        ->and(fn () => (new Path())->flatten(NAN))->toThrow(InvalidArgumentException::class, 'tolerance');
});

it('maps every point through an affine transform and keeps the curves curves', function (): void {
    $path = (new Path())->moveTo(1, 1)->cubicTo(2, 2, 3, 3, 4, 4)->close()->transformed(Affine::translation(10, 20)->multiply(Affine::scaling(2, 3)));

    expect($path->ops())->toBe([['M', 12.0, 23.0], ['C', 14.0, 26.0, 16.0, 29.0, 18.0, 32.0], ['Z']]);
});

it('measures the length of lines, a closed side included, and of a curve', function (): void {
    $square = (new Path())->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10)->lineTo(0, 10)->close();
    $open = (new Path())->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10);
    $two = (new Path())->moveTo(0, 0)->lineTo(3, 0)->moveTo(10, 10)->lineTo(10, 14);
    $quarter = (new Path())->moveTo(100, 0)->cubicTo(100, 55.2285, 55.2285, 100, 0, 100);

    expect($square->length())->toBe(40.0)
        ->and($open->length())->toBe(20.0)
        ->and($two->length())->toBe(7.0)
        ->and($quarter->length())->toEqualWithDelta(M_PI / 2 * 100, 0.2)
        ->and((new Path())->length())->toBe(0.0);
});

it('gives the point and the direction at a distance along the path, across corners and subpaths', function (): void {
    $corner = (new Path())->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10);
    $two = (new Path())->moveTo(0, 0)->lineTo(3, 0)->moveTo(10, 10)->lineTo(10, 14);

    expect($corner->pointAt(4))->toBe(['x' => 4.0, 'y' => 0.0, 'angle' => 0.0])
        ->and($corner->pointAt(15))->toBe(['x' => 10.0, 'y' => 5.0, 'angle' => M_PI / 2])
        ->and($corner->pointAt(-5)['x'])->toBe(0.0)
        ->and($corner->pointAt(99))->toBe(['x' => 10.0, 'y' => 10.0, 'angle' => M_PI / 2])
        ->and($two->pointAt(5)['y'])->toBe(12.0)
        ->and((new Path())->pointAt(1))->toBeNull();
});

it('keeps a drawn subpath with no length as its point twice, and leaves out a bare moveTo', function (): void {
    expect((new Path())->moveTo(5, 5)->lineTo(5, 5)->flatten())->toBe([['points' => [[5.0, 5.0], [5.0, 5.0]], 'closed' => false]])
        ->and((new Path())->moveTo(5, 5)->close()->flatten())->toBe([['points' => [[5.0, 5.0], [5.0, 5.0]], 'closed' => true]])
        ->and((new Path())->moveTo(5, 5)->flatten())->toBe([]);
});

it('cuts the part of a path between two distances, keeping a corner it runs round', function (): void {
    $line = (new Path())->moveTo(0, 0)->lineTo(10, 0);
    $corner = (new Path())->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10);
    $two = (new Path())->moveTo(0, 0)->lineTo(3, 0)->moveTo(10, 10)->lineTo(10, 14);

    expect($line->segment(2, 6)->ops())->toBe([['M', 2.0, 0.0], ['L', 6.0, 0.0]])
        ->and($corner->segment(5, 15)->ops())->toBe([['M', 5.0, 0.0], ['L', 10.0, 0.0], ['L', 10.0, 5.0]])
        ->and($two->segment(2, 5)->ops())->toBe([['M', 2.0, 0.0], ['L', 3.0, 0.0], ['M', 10.0, 10.0], ['L', 10.0, 12.0]])
        ->and($line->segment(-5, 50)->ops())->toBe([['M', 0.0, 0.0], ['L', 10.0, 0.0]])
        ->and($line->segment(4, 4)->isEmpty())->toBeTrue()
        ->and((new Path())->segment(0, 5)->isEmpty())->toBeTrue();
});

it('cuts a closed path through its closing side, and the cut pieces add up to the length', function (): void {
    $square = (new Path())->moveTo(0, 0)->lineTo(10, 0)->lineTo(10, 10)->lineTo(0, 10)->close();

    expect($square->segment(25, 40)->ops())->toBe([['M', 5.0, 10.0], ['L', 0.0, 10.0], ['L', 0.0, 0.0]])
        ->and($square->segment(0, 13)->length() + $square->segment(13, 40)->length())->toEqualWithDelta(40.0, 1e-9);
});

it('refuses a segment that runs backwards', function (): void {
    expect(fn () => (new Path())->moveTo(0, 0)->lineTo(10, 0)->segment(5, 2))->toThrow(InvalidArgumentException::class, 'later one');
});

it('cuts a curve into the curve between those two lengths, not into lines', function (): void {
    $cubic = (new Path())->moveTo(0, 0)->cubicTo(0, 60, 100, 60, 100, 0);
    $quad = (new Path())->moveTo(0, 0)->quadTo(50, 80, 100, 0);
    $length = $cubic->length();

    $part = $cubic->segment($length * 0.25, $length * 0.75);
    expect($part->ops()[0][0])->toBe('M')
        ->and($part->ops()[1][0])->toBe('C')
        ->and(count($part->ops()))->toBe(2)
        ->and($quad->segment(10, 60)->ops()[1][0])->toBe('Q');

    // the cut curve starts and ends where the measure says, and lies on the original
    $start = $cubic->pointAt($length * 0.25);
    $end = $cubic->pointAt($length * 0.75);
    expect($part->ops()[0][1])->toEqualWithDelta($start['x'], 1e-9)
        ->and($part->ops()[0][2])->toEqualWithDelta($start['y'], 1e-9)
        ->and($part->ops()[1][5])->toEqualWithDelta($end['x'], 1e-9)
        ->and($part->ops()[1][6])->toEqualWithDelta($end['y'], 1e-9)
        ->and($part->length())->toEqualWithDelta($length / 2, 0.15);
    $onCurve = $cubic->flatten(0.01)[0]['points'];
    foreach ($part->flatten(0.5)[0]['points'] as [$x, $y]) {
        expect(nearest($onCurve, $x, $y))->toBeLessThanOrEqual(0.05);
    }
});

it('gives the whole curve back when the segment is all of it', function (): void {
    $cubic = (new Path())->moveTo(0, 0)->cubicTo(0, 60, 100, 60, 100, 0);

    expect($cubic->segment(0, $cubic->length())->ops())->toBe($cubic->ops())
        ->and($cubic->segment(-10, 1.0e9)->ops())->toBe($cubic->ops());
});

it('cuts two curves in a row into two curves, joined without a moveTo', function (): void {
    $path = (new Path())->moveTo(0, 0)->cubicTo(0, 30, 40, 30, 40, 0)->cubicTo(40, -30, 80, -30, 80, 0);
    $half = $path->length() / 2;
    $part = $path->segment($half * 0.5, $half * 1.5);

    expect(array_column($part->ops(), 0))->toBe(['M', 'C', 'C']);
});

it('measures the direction of a curve by its tangent', function (): void {
    $cubic = (new Path())->moveTo(0, 0)->cubicTo(0, 60, 100, 60, 100, 0);

    expect($cubic->pointAt(0)['angle'])->toEqualWithDelta(M_PI / 2, 1e-9)
        ->and($cubic->pointAt($cubic->length())['angle'])->toEqualWithDelta(-M_PI / 2, 1e-9);
});

it('maps a path as the builder would, point by point, and goes on from where it ended', function () {
    $path = (new Path())->moveTo(1, 2)->lineTo(5, 2)->quadTo(7, 3, 6, 6)->close()->lineTo(9, 9)->cubicTo(10, 1, 11, 2, 12, 3);
    $matrix = new Affine(1.5, 0.25, -0.5, 2.0, 10.0, -4.0);

    $by_builder = new Path();
    foreach ($path->ops() as $op) {
        $points = [];
        for ($i = 1; $i < count($op); $i += 2) {
            $points = [...$points, ...$matrix->apply($op[$i], $op[$i + 1])];
        }
        match ($op[0]) {
            'M' => $by_builder->moveTo(...$points),
            'L' => $by_builder->lineTo(...$points),
            'Q' => $by_builder->quadTo(...$points),
            'C' => $by_builder->cubicTo(...$points),
            'Z' => $by_builder->close(),
        };
    }
    $mapped = $path->transformed($matrix);

    expect($mapped->ops())->toBe($by_builder->ops());
    // both go on the same way: a segment after a close begins with a move to the start of the subpath
    expect($mapped->close()->lineTo(0, 0)->ops())->toBe($by_builder->close()->lineTo(0, 0)->ops());
});

it('names the point a transform sends past the finite', function () {
    (new Path())->moveTo(1, 1)->lineTo(2, 2)->transformed(new Affine(1.0e308, 0.0, 0.0, 1.0, 0.0, 0.0)->multiply(new Affine(10.0, 0.0, 0.0, 1.0, 0.0, 0.0)));
})->throws(InvalidArgumentException::class);

it('gives the box of its points and control points, and forgets it when a segment is added', function () {
    $path = (new Path())->moveTo(2, 3)->cubicTo(-4, 10, 8, 12, 6, 1)->close();

    expect($path->bounds())->toBe([-4.0, 1.0, 8.0, 12.0]);
    expect($path->lineTo(20, -5)->bounds())->toBe([-4.0, -5.0, 20.0, 12.0])
        ->and((new Path())->bounds())->toBeNull();
});

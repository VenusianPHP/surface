<?php

declare(strict_types=1);

use Surface\Contracts\Drawing\Effects\Blur;
use Surface\Contracts\Drawing\Effects\Composite;
use Surface\Contracts\Drawing\Effects\CompositeOperator;
use Surface\Contracts\Drawing\Effects\FilterChain;
use Surface\Contracts\Drawing\Effects\Flood;
use Surface\Contracts\Drawing\Effects\Noise;
use Surface\Drawing\Gpu\FilterPlan;
use Surface\Framebuffers\Native\NativeFilters;
use Surface\NutsAndBolts\Color;

/*
 * FilterPlan: a chain as the passes every GPU device runs. The kinds, what each reads, and the numbers worked out here.
 */

function planKinds(array $plan): array
{
    return array_map(fn (array $p): int => $p['kind'], $plan);
}

it('only straightens an empty chain over premultiplied pixels, and premultiplies straight ones first', function (): void {
    expect(planKinds(FilterPlan::of(FilterChain::of(), true, [], [], 8, 8)))->toBe([FilterPlan::STRAIGHTEN])
        ->and(planKinds(FilterPlan::of(FilterChain::of(), false, [], [], 8, 8)))->toBe([FilterPlan::PREMULTIPLY, FilterPlan::STRAIGHTEN]);
    $plan = FilterPlan::of(FilterChain::of(), false, [], [], 8, 8);
    expect($plan[1]['in'])->toBe(1);
});

it('runs a gaussian as three boxes an axis from a deviation of 2, a kernel below, and nothing for none', function (): void {
    $boxes = FilterPlan::of(FilterChain::of(Blur::gaussian(2.5, 2.5)), true, [], [], 8, 8);
    expect(planKinds($boxes))->toBe([...array_fill(0, 6, FilterPlan::BOX), FilterPlan::STRAIGHTEN])
        ->and(array_map(fn (array $p): array => array_slice($p['params'], 0, 3), array_slice($boxes, 0, 6)))->toBe([[0.0, 5.0, 2.0], [0.0, 5.0, 2.0], [0.0, 5.0, 2.0], [1.0, 5.0, 2.0], [1.0, 5.0, 2.0], [1.0, 5.0, 2.0]])
        ->and(array_map(fn (array $p): int => $p['in'], $boxes))->toBe([0, 1, 2, 3, 4, 5, 6]);

    $kernel = FilterPlan::of(FilterChain::of(Blur::gaussian(1.2, 0.0)), true, [], [], 8, 8);
    expect(planKinds($kernel))->toBe([FilterPlan::KERNEL, FilterPlan::STRAIGHTEN])
        ->and(array_sum($kernel[0]['data']))->toBe(256.0)
        ->and($kernel[0]['params'][1])->toBe(4.0);
    expect(planKinds(FilterPlan::of(FilterChain::of(Blur::gaussian(0.0)), true, [], [], 8, 8)))->toBe([FilterPlan::STRAIGHTEN]);
});

it('gives a spiral its offsets, and names a result for a later step to read', function (): void {
    $plan = FilterPlan::of(FilterChain::of(new Flood(Color::rgb(10, 20, 30), 'f'), Blur::spiral(4.0, 5, result: 's'), new Composite('f', CompositeOperator::OVER, in: 's')), true, [], [], 8, 8);

    expect(planKinds($plan))->toBe([FilterPlan::FLOOD, FilterPlan::SPIRAL, FilterPlan::COMPOSITE, FilterPlan::STRAIGHTEN])
        ->and($plan[1]['in'])->toBe(1)
        ->and(count($plan[1]['data']))->toBe(10)
        ->and($plan[1]['data'][0])->toEqualWithDelta(NativeFilters::spiralOffsets(4.0, 5)[0][0], 1e-12)
        ->and($plan[2]['in'])->toBe(2)->and($plan[2]['in2'])->toBe(1)
        ->and($plan[3]['in'])->toBe(3);
});

it('splits a hash seed in halves a float holds, and hands a turbulence its lattice as floats', function (): void {
    $hash = FilterPlan::of(FilterChain::of(Noise::hash(-1)), true, [], [], 8, 8);
    expect($hash[0]['params'])->toBe([65535.0, 65535.0]);

    $turbulence = FilterPlan::of(FilterChain::of(Noise::fractal(0.1, 0.2, 3, 5)), true, [], [0 => NativeFilters::noiseTables(5)], 8, 8);
    expect(count($turbulence[0]['data']))->toBe(514 + 4 * 514 * 2)
        ->and($turbulence[0]['params'])->toBe([1.0, 3.0, 0.1, 0.2])
        ->and($turbulence[0]['data'][0])->toBeGreaterThanOrEqual(0.0)->toBeLessThan(256.0);
});

it('says for each result the last pass that reads it, and keeps the final one for the copy back', function (): void {
    $plan = FilterPlan::of(FilterChain::of(Blur::gaussian(2.5, 2.5)), true, [], [], 8, 8);
    $last = FilterPlan::lastReads($plan);
    expect($last)->not->toHaveKey(0)
        ->and($last[1])->toBe(1)
        ->and($last[count($plan)])->toBe(PHP_INT_MAX);
    foreach ($plan as $i => $pass) {
        foreach ([$pass['in'], $pass['in2']] as $read) {
            if ($read > 0) {
                expect($last[$read])->toBeGreaterThanOrEqual($i);
            }
        }
    }
});

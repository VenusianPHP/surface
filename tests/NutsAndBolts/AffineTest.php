<?php

declare(strict_types=1);

use Surface\NutsAndBolts\Affine;

it('starts as identity and maps points as x′ = ax + cy + e, y′ = bx + dy + f', function (): void {
    $m = new Affine(2.0, 3.0, 5.0, 7.0, 11.0, 13.0);

    expect(Affine::identity()->apply(4.0, -9.0))->toBe([4.0, -9.0])
        ->and(Affine::identity()->toArray())->toBe([1.0, 0.0, 0.0, 1.0, 0.0, 0.0])
        ->and($m->apply(1.0, 1.0))->toBe([18.0, 23.0])
        ->and($m->toArray())->toBe([2.0, 3.0, 5.0, 7.0, 11.0, 13.0]);
});

it('builds translations, scalings and rotations', function (): void {
    [$x, $y] = Affine::rotation(M_PI / 2)->apply(1.0, 0.0);

    expect(Affine::translation(3.0, 4.0)->apply(1.0, 1.0))->toBe([4.0, 5.0])
        ->and(Affine::scaling(2.0, 3.0)->apply(1.0, 1.0))->toBe([2.0, 3.0])
        ->and(abs($x) < 1e-12 && abs($y - 1.0) < 1e-12)->toBeTrue();   // +x turns towards +y: clockwise on a y-down surface
});

it('multiplies so the right-hand transform applies first', function (): void {
    $m = Affine::translation(10.0, 0.0)->multiply(Affine::scaling(2.0, 2.0));

    expect($m->apply(1.0, 1.0))->toBe([12.0, 2.0])
        ->and(Affine::scaling(2.0, 2.0)->multiply(Affine::translation(10.0, 0.0))->apply(1.0, 1.0))->toBe([22.0, 2.0]);
});

it('inverts, and answers null when it cannot', function (): void {
    $m = Affine::translation(5.0, -3.0)->multiply(Affine::scaling(2.0, 4.0));
    $back = $m->inverse();

    expect($back->apply(...$m->apply(7.0, 9.0)))->toBe([7.0, 9.0])
        ->and($m->determinant())->toBe(8.0)
        ->and(Affine::scaling(0.0, 1.0)->inverse())->toBeNull()
        ->and(Affine::scaling(0.0, 1.0)->determinant())->toBe(0.0);
});

it('knows when it keeps axes on axes', function (): void {
    expect(Affine::translation(1.0, 2.0)->multiply(Affine::scaling(-2.0, 3.0))->isAxisAligned())->toBeTrue()
        ->and(Affine::rotation(0.3)->isAxisAligned())->toBeFalse();
});

it('refuses components that are not finite', function (): void {
    expect(fn () => new Affine(NAN, 0.0, 0.0, 1.0, 0.0, 0.0))->toThrow(InvalidArgumentException::class, 'Affine components are finite')
        ->and(fn () => Affine::translation(INF, 0.0))->toThrow(InvalidArgumentException::class);
});

<?php

use Surface\Drawing\Affine;

it('composes like Painter did: the right operand applies to a point first', function () {
    $t = Affine::identity()->compose(Affine::translation(100.0, 0.0))->compose(Affine::rotation(M_PI / 2));
    [$x, $y] = $t->apply(10.0, 0.0);

    expect(round($x, 6))->toBe(100.0)->and(round($y, 6))->toBe(10.0)
        ->and(Affine::identity()->isTranslation())->toBeTrue()
        ->and(Affine::translation(3.0, 4.0)->isTranslation())->toBeTrue()
        ->and(Affine::scaling(2.0, 2.0)->isTranslation())->toBeFalse();
});

it('inverts, and a singular transform inverts to null', function () {
    $t = Affine::translation(5.0, 7.0)->compose(Affine::scaling(2.0, 4.0));
    [$x, $y] = $t->invert()->apply(...$t->apply(3.0, 3.0));

    expect(round($x, 9))->toBe(3.0)->and(round($y, 9))->toBe(3.0)
        ->and(Affine::scaling(0.0, 1.0)->invert())->toBeNull();
});

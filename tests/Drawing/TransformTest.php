<?php

use Surface\Contracts\Drawing\Transform;

it('identity packs to 64 bytes of a column-major identity', function () {
    $packed = Transform::identity()->toPacked();

    expect(strlen($packed))->toBe(64)
        ->and(array_values(unpack('g16', $packed)))->toBe([
            1.0, 0.0, 0.0, 0.0,
            0.0, 1.0, 0.0, 0.0,
            0.0, 0.0, 1.0, 0.0,
            0.0, 0.0, 0.0, 1.0,
        ]);
});

it('orthographic maps top-left pixels to canonical clip space, y up', function () {
    $ortho = Transform::orthographic(640, 480);

    expect($ortho->apply(0.0, 0.0))->toBe([-1.0, 1.0])
        ->and($ortho->apply(640.0, 480.0))->toBe([1.0, -1.0])
        ->and($ortho->apply(320.0, 240.0))->toBe([0.0, 0.0]);
});

it('translate then rotate composes in call order', function () {
    $t = Transform::identity()->translate(10.0, 0.0)->rotate(M_PI / 2);
    [$x, $y] = $t->apply(1.0, 0.0);

    expect(round($x, 6))->toBe(10.0)
        ->and(round($y, 6))->toBe(1.0);
});

it('scale multiplies the axes independently', function () {
    expect(Transform::identity()->scale(2.0, 3.0)->apply(1.0, 1.0))->toBe([2.0, 3.0]);
});

it('multiply applies the right operand first', function () {
    $a = Transform::identity()->translate(5.0, 0.0);
    $b = Transform::identity()->scale(2.0, 2.0);

    expect($a->multiply($b)->apply(1.0, 1.0))->toBe([7.0, 2.0]);
});

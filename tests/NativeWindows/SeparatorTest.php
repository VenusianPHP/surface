<?php

use Venusian\Surface\Tests\Support\Fakes\FakeSeparator;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a wide frame as a horizontal separator', function () {
    $window = new FakeWindow('main');

    $line = $window->separator('divider', 10, 100, 300, 1);

    expect($line)->toBeInstanceOf(FakeSeparator::class)
        ->and($line->isHorizontal())->toBeTrue()
        ->and($line->applied_frames)->toBe([[10, 100, 300, 1]]);
});

it('conjures a tall frame as a vertical separator', function () {
    $window = new FakeWindow('main');

    $line = $window->separator('divider', 200, 0, 1, 480);

    expect($line->isHorizontal())->toBeFalse();
});

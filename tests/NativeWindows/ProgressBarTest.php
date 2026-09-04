<?php

use Venusian\Surface\Tests\Support\Fakes\FakeProgressBar;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

it('conjures a progress bar clamped into 0..1 and places it at once', function () {
    $window = new FakeWindow('main');

    $bar = $window->progressBar('download', 1.7, 10, 20, 300, 12);

    expect($bar)->toBeInstanceOf(FakeProgressBar::class)
        ->and($bar->progress())->toBe(1.0)
        ->and($bar->applied_frames)->toBe([[10, 20, 300, 12]]);
});

it('setProgress clamps and writes through', function () {
    $window = new FakeWindow('main');
    $bar = $window->progressBar('download', 0.0, 0, 0, 300, 12);

    $bar->setProgress(0.4);
    $bar->setProgress(-2.0);

    expect($bar->progress())->toBe(0.0)
        ->and($bar->applied_progress)->toBe([0.4, 0.0]);
});

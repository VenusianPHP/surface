<?php

declare(strict_types=1);

use Surface\Framebuffers\Native\NativeFramebufferDriver;
use Venusian\Surface\Tests\Support\Framebuffers\FixtureRunner;

it('passes every golden fixture with its bytes in PHP', function (string $file): void {
    FixtureRunner::run(new NativeFramebufferDriver(), $file);
})->with(FixtureRunner::files());

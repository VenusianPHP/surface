<?php

declare(strict_types=1);

use Surface\Framebuffers\Extended\ExtendedFramebufferDriver;
use Venusian\Surface\Tests\Support\Framebuffers\FixtureRunner;

it('passes every golden fixture with its bytes in ext-fb', function (string $file): void {
    FixtureRunner::run(new ExtendedFramebufferDriver(), $file);
})->with(FixtureRunner::files())->skip(! class_exists(FbBuffer::class), 'ext-fb 0.10 is not loaded in this PHP.');

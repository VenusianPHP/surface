<?php

use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Framebuffers\Php\PhpFramebufferDriver;
use Venusian\Surface\Tests\Support\Framebuffers\FixtureRunner;

/** Until Task 4 lands the other kinds, a driver that mints full buffers and refuses the rest. */
function phpDriver(): FramebufferDriver
{
    return new PhpFramebufferDriver();
}

it('passes every golden fixture', function (string $file) {
    FixtureRunner::run(phpDriver(), $file);
})->with(fn () => array_filter(
    array_combine(array_map('basename', FixtureRunner::files()), FixtureRunner::files()),
    fn (string $f) => json_decode(file_get_contents($f), true)['kind'] === 'full',
));

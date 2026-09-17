<?php

use Surface\Contracts\Framebuffers\FramebufferDriver;
use Surface\Framebuffers\Php\PhpFramebufferDriver;
use Venusian\Surface\Tests\Support\Framebuffers\FixtureRunner;

function phpDriver(): FramebufferDriver
{
    return new PhpFramebufferDriver();
}

it('passes every golden fixture', function (string $file) {
    FixtureRunner::run(phpDriver(), $file);
})->with(fn () => array_combine(array_map('basename', FixtureRunner::files()), FixtureRunner::files()));

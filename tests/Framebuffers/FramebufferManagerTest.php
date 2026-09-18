<?php

use Psr\Container\NotFoundExceptionInterface;
use Surface\Framebuffers\FramebufferManager;
use Surface\Framebuffers\Php\PhpFramebufferDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;

function fbManager(array $config, array $bindings = []): FramebufferManager
{
    $vessel = new FakeBindingVessel(['config' => new FakeConfigRepository(['framebuffers' => $config])] + $bindings);

    return new FramebufferManager($vessel);
}

it('defaults to php and builds it in-house', function () {
    $m = fbManager([]);

    expect($m->getDefaultDriver())->toBe('php')
        ->and($m->driver())->toBeInstanceOf(PhpFramebufferDriver::class)
        ->and($m->driver()->driver())->toBe('php');
});

it('resolves native through its configured container alias', function () {
    $native = new PhpFramebufferDriver();   // any FramebufferDriver stands in for the native one
    $m = fbManager(['default' => 'native', 'drivers' => ['native' => ['alias' => 'framebuffer.native']]], ['framebuffer.native' => $native]);

    expect($m->driver())->toBe($native);
});

it('a missing native package is the container not-found', function () {
    $m = fbManager(['default' => 'native', 'drivers' => ['native' => ['alias' => 'framebuffer.native']]]);

    try {
        $m->driver('native');
        expect(false)->toBeTrue();
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(NotFoundExceptionInterface::class);
    }
});

it('honours a rebound php alias', function () {
    $other = new PhpFramebufferDriver();
    $m = fbManager(['drivers' => ['php' => ['alias' => 'framebuffer.my-php']]], ['framebuffer.my-php' => $other]);

    expect($m->driver('php'))->toBe($other);
});

it('an unknown driver name is the Manager\'s InvalidArgumentException', function () {
    expect(fn () => fbManager([])->driver('gpu'))->toThrow(InvalidArgumentException::class);
});

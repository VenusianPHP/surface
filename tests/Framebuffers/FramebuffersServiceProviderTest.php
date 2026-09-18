<?php

use Surface\Framebuffers\FramebufferManager;
use Surface\Framebuffers\FramebuffersServiceProvider;
use Surface\Framebuffers\Php\PhpFramebufferDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;

it('merges the config, binds the manager and the php driver', function () {
    $app = new FakeBindingVessel(['config' => new FakeConfigRepository()]);
    (new FramebuffersServiceProvider($app))->register();

    expect($app->get('config')->get('framebuffers.default'))->toBe('php')
        ->and($app->get('config')->get('framebuffers.drivers.native.alias'))->toBe('framebuffer.native')
        ->and($app->bindings['framebuffer.php'])->toBeInstanceOf(PhpFramebufferDriver::class)
        ->and($app->bindings[FramebufferManager::class])->toBeInstanceOf(FramebufferManager::class)
        ->and($app->bindings['framebuffers'])->toBe($app->bindings[FramebufferManager::class]);
});

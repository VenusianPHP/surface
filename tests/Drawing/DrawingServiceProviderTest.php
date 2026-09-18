<?php

use Surface\Drawing\CPUEngineManager;
use Surface\Drawing\DrawingServiceProvider;
use Surface\Drawing\Engines\DirtyEngine;
use Surface\Drawing\Engines\NFramesEngine;
use Surface\Drawing\GPUEngineManager;
use Surface\Framebuffers\FramebufferManager;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;

it('binds both managers and the five cpu engine aliases', function () {
    $app = new FakeBindingVessel(['config' => new FakeConfigRepository()]);
    $app->instance(FramebufferManager::class, new FramebufferManager($app));
    (new DrawingServiceProvider($app))->register();

    expect($app->bindings['gpu-engines'])->toBeInstanceOf(GPUEngineManager::class)
        ->and($app->bindings['cpu-engines'])->toBeInstanceOf(CPUEngineManager::class)
        ->and($app->bindings['cpu.dirty'])->toBeInstanceOf(DirtyEngine::class)
        ->and($app->bindings['cpu.nframes'])->toBeInstanceOf(NFramesEngine::class)
        ->and($app->get('config')->get('cpu.default'))->toBe('dirty')
        ->and($app->get('config')->get('cpu.engines.epaper.alias'))->toBe('cpu.epaper');
});

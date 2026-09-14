<?php

use Psr\Container\NotFoundExceptionInterface;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Drawing\GPUEngineManager;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUEngineDriver;

function gpuManager(array $config, array $bindings = []): GPUEngineManager
{
    $vessel = new FakeBindingVessel(['config' => new FakeConfigRepository(['gpu' => $config])] + $bindings);

    return new GPUEngineManager($vessel);
}

it('resolves metal through its configured container alias', function () {
    $metal = new FakeGPUEngineDriver(GPUEngine::METAL);
    $manager = gpuManager(
        ['default' => 'metal', 'engines' => ['metal' => ['alias' => 'gpu.metal']]],
        ['gpu.metal' => $metal],
    );

    expect($manager->driver('metal'))->toBe($metal)
        ->and($manager->driver())->toBe($metal);
});

it('honours a rebound alias without code', function () {
    $other = new FakeGPUEngineDriver(GPUEngine::OPENGL);
    $manager = gpuManager(
        ['default' => 'opengl', 'engines' => ['opengl' => ['alias' => 'gpu.my-gl']]],
        ['gpu.my-gl' => $other],
    );

    expect($manager->driver('opengl'))->toBe($other);
});

it('a missing engine package surfaces as the container not-found', function () {
    $manager = gpuManager(['default' => 'vulkan', 'engines' => []]);

    try {
        $manager->driver('vulkan');
        expect(false)->toBeTrue();
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(NotFoundExceptionInterface::class);
    }
});

it('an unknown engine name is the Manager\'s InvalidArgumentException', function () {
    $manager = gpuManager(['default' => 'metal']);

    expect(fn () => $manager->driver('directx'))->toThrow(InvalidArgumentException::class);
});

it('extend() registers a custom engine', function () {
    $custom = new FakeGPUEngineDriver(GPUEngine::SDL3);
    $manager = gpuManager(['default' => 'metal']);
    $manager->extend('custom', fn () => $custom);

    expect($manager->driver('custom'))->toBe($custom);
});

it('falls back to metal on mac and opengl elsewhere when config has no default', function () {
    $manager = gpuManager([]);

    expect($manager->getDefaultDriver())->toBe(device_os_family() === 'mac' ? 'metal' : 'opengl');
});

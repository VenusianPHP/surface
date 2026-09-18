<?php

use Psr\Container\NotFoundExceptionInterface;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Drawing\CPUEngineManager;
use Venusian\Surface\Tests\Support\Fakes\FakeBindingVessel;
use Venusian\Surface\Tests\Support\Fakes\FakeConfigRepository;
use Venusian\Surface\Tests\Support\Fakes\FakeCPUEngineDriver;

function cpuManager(array $config, array $bindings = []): CPUEngineManager
{
    $vessel = new FakeBindingVessel(['config' => new FakeConfigRepository(['cpu' => $config])] + $bindings);

    return new CPUEngineManager($vessel);
}

it('resolves each engine through its configured alias and defaults to dirty', function () {
    $dirty = new FakeCPUEngineDriver(CPUEngine::DIRTY);
    $paged = new FakeCPUEngineDriver(CPUEngine::PAGED);
    $m = cpuManager(
        ['engines' => ['dirty' => ['alias' => 'cpu.dirty'], 'paged' => ['alias' => 'cpu.paged']]],
        ['cpu.dirty' => $dirty, 'cpu.paged' => $paged],
    );

    expect($m->getDefaultDriver())->toBe('dirty')
        ->and($m->driver())->toBe($dirty)
        ->and($m->driver('paged'))->toBe($paged);
});

it('a missing engine binding is the container not-found; an unknown name is InvalidArgumentException', function () {
    $m = cpuManager(['engines' => ['nframes' => ['alias' => 'cpu.nframes']]]);

    try {
        $m->driver('nframes');
        expect(false)->toBeTrue();
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(NotFoundExceptionInterface::class);
    }
    expect(fn () => $m->driver('gpu'))->toThrow(InvalidArgumentException::class);
});

it('extend() registers a custom engine', function () {
    $custom = new FakeCPUEngineDriver(CPUEngine::FULL);
    $m = cpuManager([]);
    $m->extend('custom', fn () => $custom);

    expect($m->driver('custom'))->toBe($custom);
});

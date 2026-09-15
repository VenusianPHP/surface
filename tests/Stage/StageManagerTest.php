<?php

use Psr\Container\NotFoundExceptionInterface;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Stage\Events\StageResized;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageHost;
use Surface\Stage\StageManager;
use Surface\Stage\StageResourceDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUEngineDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeExecutor;
use Venusian\Surface\Tests\Support\Fakes\FakeStageSession;

it('opens on the configured default host, connecting it and attaching the named engine', function () {
    $session = new FakeStageSession();
    [$stages] = stageManager(['default' => 'sdl3'], ['stage.sdl3' => $session]);

    $stage = $stages->open('main', 'opengl', 640, 480);

    expect($session->connected())->toBeTrue()
        ->and($stage->engine())->toBe(GPUEngine::OPENGL)
        ->and($stages->get('main'))->toBe($stage)
        ->and($stages->has('main'))->toBeTrue();
});

it('takes the host and engine as enums, and the default engine as null', function () {
    $session = new FakeStageSession(StageHost::APPKIT);
    [$stages] = stageManager(['default' => 'sdl3'], ['stage.appkit' => $session]);

    $stage = $stages->open('main', null, 640, 480, StageHost::APPKIT);

    expect($stage->engine())->toBe(GPUEngine::METAL)
        ->and($session->minted)->toHaveCount(1);
});

it('honours a rebound host alias without code', function () {
    $session = new FakeStageSession();
    [$stages] = stageManager(['default' => 'sdl3', 'hosts' => ['sdl3' => ['alias' => 'stage.my-sdl']]], ['stage.my-sdl' => $session]);

    $stages->open('main', 'metal', 10, 10);

    expect($session->minted)->toHaveCount(1);
});

it('hands every stage the dock, so its mail reaches the sketch', function () {
    [$stages, $dock] = stageManager(bindings: ['stage.sdl3' => new FakeStageSession()]);
    $stage = $stages->open('main', 'metal', 640, 480);

    $stage->resized(800, 600, 1.0);

    expect($dock->drain()->first())->toBeInstanceOf(StageResized::class);
});

it('registers one dock resource per host, tracking every stage it opened', function () {
    [$stages, $dock] = stageManager(bindings: ['stage.sdl3' => new FakeStageSession()]);
    $stages->open('a', 'metal', 10, 10);
    $stages->open('b', 'metal', 10, 10);

    $resource = $dock->resources()->get('stage.sdl3');

    expect($resource)->toBeInstanceOf(StageResourceDriver::class)
        ->and(array_keys($resource->stages()))->toBe(['a', 'b']);
});

it('guards the name, and frees it once the stage is closed', function () {
    [$stages] = stageManager(bindings: ['stage.sdl3' => new FakeStageSession()]);
    $stage = $stages->open('main', 'metal', 10, 10);

    expect(fn () => $stages->open('main', 'metal', 10, 10))->toThrow(StageException::class, "Stage 'main' already exists.");

    $stage->close();
    expect($stages->has('main'))->toBeFalse()
        ->and(fn () => $stages->get('main'))->toThrow(StageException::class, "No stage named 'main'.")
        ->and($stages->open('main', 'metal', 10, 10)->isOpen())->toBeTrue();
});

it('a missing host package surfaces as the container not-found', function () {
    [$stages] = stageManager(['default' => 'glfw']);

    try {
        $stages->open('main', 'metal', 10, 10);
        expect(false)->toBeTrue();
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(NotFoundExceptionInterface::class);
    }
});

it('closeAll() closes every open stage', function () {
    [$stages] = stageManager(bindings: ['stage.sdl3' => new FakeStageSession()]);
    $a = $stages->open('a', 'metal', 10, 10);
    $b = $stages->open('b', 'metal', 10, 10);

    $stages->closeAll();

    expect($a->isOpen())->toBeFalse()
        ->and($b->isOpen())->toBeFalse()
        ->and($stages->all())->toBe([]);
});

it('destroy() closes every stage, then disconnects every host session it created', function () {
    $sdl = new FakeStageSession();
    $appkit = new FakeStageSession(StageHost::APPKIT);
    [$stages] = stageManager(bindings: ['stage.sdl3' => $sdl, 'stage.appkit' => $appkit]);
    $a = $stages->open('a', 'metal', 10, 10);
    $b = $stages->open('b', 'metal', 10, 10, StageHost::APPKIT);

    $stages->destroy();

    expect($a->isOpen())->toBeFalse()
        ->and($b->isOpen())->toBeFalse()
        ->and($a->executor()->released)->toBeTrue()
        ->and($stages->all())->toBe([])
        ->and($sdl->connected())->toBeFalse()
        ->and($appkit->connected())->toBeFalse()
        ->and($sdl->engine_disconnections)->toBe(1)
        ->and($appkit->engine_disconnections)->toBe(1);
});

it('destroy() keeps going past a throwing close and a throwing disconnect, then rethrows the first', function () {
    $sdl = new FakeStageSession();
    $sdl->disconnect_failure = new RuntimeException('disconnect failed');
    $appkit = new FakeStageSession(StageHost::APPKIT);
    [$stages] = stageManager(bindings: ['stage.sdl3' => $sdl, 'stage.appkit' => $appkit]);
    $a = $stages->open('a', 'metal', 10, 10);
    $b = $stages->open('b', 'metal', 10, 10, StageHost::APPKIT);
    $executor = $a->executor();
    assert($executor instanceof FakeExecutor);
    $executor->release_failure = new RuntimeException('release failed');

    expect(fn () => $stages->destroy())->toThrow(RuntimeException::class, 'release failed');

    expect($a->isOpen())->toBeFalse()
        ->and($b->isOpen())->toBeFalse()
        ->and($b->executor()->released)->toBeTrue()
        ->and($sdl->connected())->toBeFalse()
        ->and($appkit->connected())->toBeFalse()
        ->and($appkit->engine_disconnections)->toBe(1);
});

it('destroy() with nothing opened is a no-op', function () {
    [$stages] = stageManager();

    $stages->destroy();

    expect($stages->all())->toBe([]);
});

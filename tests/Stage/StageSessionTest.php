<?php

use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageHost;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUEngineDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeStagedWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeStageSession;

it('does not start its engine until the first connect', function () {
    $session = new FakeStageSession();

    expect($session->initializations)->toBe(0)
        ->and($session->connected())->toBeFalse();
});

it('starts the engine once across connect / disconnect cycles', function () {
    $session = new FakeStageSession();
    $session->connect()->connect();
    $session->disconnect();
    $session->connect();

    expect($session->initializations)->toBe(1)
        ->and($session->engine_connections)->toBe(2)
        ->and($session->engine_disconnections)->toBe(1);
});

it('pumps only while connected and drains before disconnecting', function () {
    $session = new FakeStageSession();

    expect($session->pump(16))->toBe(0)
        ->and($session->pumps)->toBe([]);

    $session->connect();
    expect($session->pump(16))->toBe(3);

    $session->disconnect();
    expect($session->pumps)->toBe([16, 0]);
});

it('refuses to open a stage while disconnected', function () {
    $session = new FakeStageSession(StageHost::APPKIT);

    expect(fn () => $session->open('main', new FakeGPUEngineDriver(), 640, 480))
        ->toThrow(StageException::class, "The 'appkit' stage host is not connected.");
});

it('opens a stage through the host hook once connected', function () {
    $session = (new FakeStageSession(scale: 2.0))->connect();
    $stage = $session->open('main', new FakeGPUEngineDriver(GPUEngine::OPENGL), 640, 480);

    expect($stage)->toBeInstanceOf(FakeStagedWindow::class)
        ->and($stage->engine())->toBe(GPUEngine::OPENGL)
        ->and($stage->size())->toBe([640, 480])
        ->and($stage->scale())->toBe(2.0)
        ->and($stage->drawableSize())->toBe([1280, 960]);
});

it('ends disconnected even when the engine hook throws', function () {
    $session = (new FakeStageSession())->connect();
    $session->disconnect_failure = new RuntimeException('engine refused');

    expect(fn () => $session->disconnect())->toThrow(RuntimeException::class, 'engine refused')
        ->and($session->connected())->toBeFalse()
        ->and($session->pump(16))->toBe(0);

    $session->disconnect();
    expect($session->engine_disconnections)->toBe(1);
});

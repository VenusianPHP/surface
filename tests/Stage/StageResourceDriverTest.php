<?php

use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Stage\StageHost;
use Surface\Stage\StageResourceDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUEngineDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeStageSession;
use Voyager\Contracts\IOPools\IOResourceDriver;

it('pumps a host that owns its pump, then renders its open stages', function () {
    $dock = bareDock();
    $session = (new FakeStageSession())->connect();
    $resource = new StageResourceDriver($dock, $session);
    $stage = $session->open('main', new FakeGPUEngineDriver(GPUEngine::METAL), 100, 100);
    $stage->onDraw(fn () => null)->show();
    $resource->track($stage);

    $resource->tick();

    expect($session->pumps)->toBe([0])
        ->and($stage->executor()->frames_begun)->toBe(1);
});

it('does not pump a host that shares the native pump when the os resource is on the dock', function () {
    $dock = bareDock();
    $dock->resource('os', new class implements IOResourceDriver {
        public function tick(): void {}
    });
    $session = (new FakeStageSession(StageHost::APPKIT, shares_native_pump: true))->connect();
    $resource = new StageResourceDriver($dock, $session);
    $stage = $session->open('main', new FakeGPUEngineDriver(), 100, 100)->onDraw(fn () => null)->show();
    $resource->track($stage);

    $resource->tick();

    expect($session->pumps)->toBe([])
        ->and($stage->executor()->frames_begun)->toBe(1);
});

it('pumps a sharing host itself when there is no os resource', function () {
    $session = (new FakeStageSession(StageHost::APPKIT, shares_native_pump: true))->connect();
    (new StageResourceDriver(bareDock(), $session))->tick();

    expect($session->pumps)->toBe([0]);
});

it('forgets closed stages', function () {
    $session = (new FakeStageSession())->connect();
    $resource = new StageResourceDriver(bareDock(), $session);
    $stage = $session->open('main', new FakeGPUEngineDriver(), 100, 100);
    $resource->track($stage);
    $stage->close();

    $resource->tick();

    expect($resource->stages())->toBe([]);
});

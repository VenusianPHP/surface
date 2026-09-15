<?php

use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Stage\Events\StageClosed;
use Surface\Contracts\Stage\Events\StageResized;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageHost;
use Voyager\Contracts\IOPools\Occurrence;

it('names the three hosts', function () {
    expect(array_map(fn (StageHost $host) => $host->value, StageHost::cases()))->toBe(['appkit', 'sdl3', 'glfw']);
});

it('stage mail is an Occurrence named per stage', function () {
    $closed = new StageClosed('main');
    $resized = new StageResized('main', 800, 600, 2.0);

    expect($closed)->toBeInstanceOf(Occurrence::class)
        ->and($closed->name)->toBe('stage.closed.main')
        ->and($resized->name)->toBe('stage.resized.main')
        ->and([$resized->width, $resized->height, $resized->scale])->toBe([800, 600, 2.0]);
});

it('says what a host cannot do, by enum', function () {
    expect(StageException::unsupported(StageHost::APPKIT, GPUEngine::SDL3, SurfaceKind::HOST_WINDOW)->getMessage())
        ->toBe("The 'appkit' stage host cannot give a 'sdl3' engine a HOST_WINDOW surface.")
        ->and(StageException::nameTaken('main')->getMessage())->toBe("Stage 'main' already exists.")
        ->and(StageException::noSuchStage('main')->getMessage())->toBe("No stage named 'main'.")
        ->and(StageException::notConnected(StageHost::SDL3)->getMessage())->toBe("The 'sdl3' stage host is not connected.")
        ->and(StageException::closed('main')->getMessage())->toBe("Stage 'main' is closed.");
});

it('names host and engine when an engine attach fails, chaining the engine error', function () {
    $engine_error = new RuntimeException('no device');
    $e = StageException::attachFailed(StageHost::SDL3, GPUEngine::VULKAN, $engine_error);

    expect($e)->toBeInstanceOf(StageException::class)
        ->and($e->getMessage())->toBe("The 'sdl3' stage host could not attach a 'vulkan' engine: no device")
        ->and($e->getPrevious())->toBe($engine_error);
});

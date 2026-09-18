<?php

use Surface\Contracts\Drawing\CPUDrawTarget;
use Surface\Contracts\Drawing\DrawTarget;
use Surface\Contracts\Drawing\GPUDrawTarget;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Drawing\SurfaceKind;
use Surface\Contracts\Stage\CPUStagedWindow;
use Surface\Contracts\Stage\Events\StageClosed;
use Surface\Contracts\Stage\Events\StageResized;
use Surface\Contracts\Stage\GPUStagedWindow;
use Surface\Contracts\Stage\StagedWindow;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageFit;
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

it('the staged window contract is engine-free; the two kinds add their engine', function () {
    expect(is_subclass_of(StagedWindow::class, DrawTarget::class))->toBeTrue()
        ->and(is_subclass_of(StagedWindow::class, GPUDrawTarget::class))->toBeFalse()
        ->and(method_exists(StagedWindow::class, 'executor'))->toBeFalse()
        ->and(is_subclass_of(GPUStagedWindow::class, StagedWindow::class))->toBeTrue()
        ->and(is_subclass_of(GPUStagedWindow::class, GPUDrawTarget::class))->toBeTrue()
        ->and(is_subclass_of(CPUStagedWindow::class, StagedWindow::class))->toBeTrue()
        ->and(is_subclass_of(CPUStagedWindow::class, CPUDrawTarget::class))->toBeTrue()
        ->and(method_exists(CPUStagedWindow::class, 'canvasSize'))->toBeTrue()
        ->and(method_exists(CPUStagedWindow::class, 'fit'))->toBeTrue();
});

it('the GPU stage class and its hosts still satisfy the GPU contract', function () {
    expect(is_subclass_of(\Surface\Stage\StagedWindow::class, GPUStagedWindow::class))->toBeTrue();
});

it('StageFit names four scalers by string', function () {
    expect(array_map(fn (StageFit $f) => $f->value, StageFit::cases()))
        ->toBe(['stretch', 'letterbox', 'integer_scale', 'overscan'])
        ->and(StageFit::from('integer_scale'))->toBe(StageFit::INTEGER_SCALE);
});

it('a host that cannot present a CPU canvas says so by enum', function () {
    expect(StageException::cpuUnsupported(StageHost::APPKIT)->getMessage())
        ->toBe("The 'appkit' stage host cannot present a CPU canvas.");
});

<?php

use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\CPUHost;
use Surface\Contracts\Framebuffers\BitDepth;
use Surface\Contracts\Framebuffers\FormatSpec;
use Surface\Contracts\Framebuffers\PixelFormat;
use Surface\Contracts\Stage\Events\StageResized;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageFit;
use Surface\Stage\StageResourceDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeStageSession;

function panelHost(int $w = 128, int $h = 64): CPUHost
{
    return new CPUHost($w, $h, new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1));
}

it('opens a CPU stage on the configured host with the named engine', function () {
    $session = new FakeStageSession();
    [$stages] = stageManager(['default' => 'sdl3'], ['stage.sdl3' => $session]);

    $stage = $stages->openCPU('oled', 'dirty', panelHost(), 512, 256);

    expect($session->connected())->toBeTrue()
        ->and($session->cpu_minted)->toHaveCount(1)
        ->and($stage->engine())->toBe(CPUEngine::DIRTY)
        ->and($stage->size())->toBe([512, 256])
        ->and($stage->canvasSize())->toBe([128, 64])
        ->and($stage->fit())->toBe(StageFit::INTEGER_SCALE)
        ->and($stages->get('oled'))->toBe($stage);
});

it('emulate() zooms the panel by whole pixels', function () {
    [$stages] = stageManager(bindings: ['stage.sdl3' => new FakeStageSession()]);

    $stage = $stages->emulate('oled', panelHost(), zoom: 4);

    expect($stage->size())->toBe([512, 256])
        ->and($stage->canvasSize())->toBe([128, 64])
        ->and($stage->fit())->toBe(StageFit::INTEGER_SCALE)
        ->and($stage->engine())->toBe(CPUEngine::DIRTY);
});

it('emulate() refuses a zoom below one', function () {
    [$stages] = stageManager(bindings: ['stage.sdl3' => new FakeStageSession()]);

    expect(fn () => $stages->emulate('oled', panelHost(), zoom: 0))->toThrow(StageException::class);
});

it('takes the fit as an enum or a string, and reads the config default when null', function () {
    [$stages] = stageManager(['default' => 'sdl3', 'cpu_fit' => 'letterbox'], ['stage.sdl3' => new FakeStageSession()]);

    expect($stages->openCPU('a', 'dirty', panelHost(), 256, 128)->fit())->toBe(StageFit::LETTERBOX)
        ->and($stages->openCPU('b', 'dirty', panelHost(), 256, 128, fit: StageFit::STRETCH)->fit())->toBe(StageFit::STRETCH)
        ->and($stages->openCPU('c', 'dirty', panelHost(), 256, 128, fit: 'overscan')->fit())->toBe(StageFit::OVERSCAN);
});

it('a host that cannot present a canvas refuses by contract', function () {
    $session = new FakeStageSession();
    $session->refuses_cpu = true;
    [$stages] = stageManager(bindings: ['stage.sdl3' => $session]);

    expect(fn () => $stages->openCPU('oled', 'dirty', panelHost(), 512, 256))
        ->toThrow(StageException::class, "The 'sdl3' stage host cannot present a CPU canvas.");
});

it('a taken name is refused whichever kind holds it', function () {
    [$stages] = stageManager(bindings: ['stage.sdl3' => new FakeStageSession()]);
    $stages->open('main', 'metal', 640, 480);

    expect(fn () => $stages->openCPU('main', 'dirty', panelHost(), 512, 256))->toThrow(StageException::class);
});

it('both kinds land in the registry, the dock and one resource driver', function () {
    [$stages, $dock] = stageManager(bindings: ['stage.sdl3' => new FakeStageSession()]);
    $gpu = $stages->open('main', 'metal', 640, 480);
    $cpu = $stages->openCPU('oled', 'dirty', panelHost(), 512, 256);

    $resource = $dock->resources()->get('stage.sdl3');
    expect($resource)->toBeInstanceOf(StageResourceDriver::class)
        ->and($resource->stages())->toHaveCount(2)
        ->and($stages->all())->toHaveCount(2);

    $cpu->resized(800, 600, 1.0);
    expect($dock->drain()->filter(fn ($m) => $m instanceof StageResized))->toHaveCount(1);

    $stages->closeAll();
    expect($gpu->isOpen())->toBeFalse()->and($cpu->isOpen())->toBeFalse();
});

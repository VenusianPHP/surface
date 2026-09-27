<?php

use Surface\Contracts\Drawing\Color;
use Surface\Contracts\Drawing\CPUEngine;
use Surface\Contracts\Drawing\Drawing2D;
use Surface\Contracts\Drawing\GPUEngine;
use Surface\Contracts\Stage\StageException;
use Surface\Contracts\Stage\StageFit;
use Surface\Drawing\Engines\DirtyEngine;
use Surface\Drawing\Engines\FullEngine;
use Surface\Drawing\GPUCanvas;
use Surface\EmbeddedDisplays\EmbeddedDisplay;
use Surface\Framebuffers\Php\PhpFramebufferDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeCPUStagedWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeDisplayPanel;
use Venusian\Surface\Tests\Support\Fakes\FakeEngineCanvas;
use Venusian\Surface\Tests\Support\Fakes\FakeExecutor;
use Venusian\Surface\Tests\Support\Fakes\FakeGPUEngineDriver;
use Venusian\Surface\Tests\Support\Fakes\FakeStagedWindow;
use Venusian\Surface\Tests\Support\Fakes\FakeWindow;

/** The real CPU engines, under the names a sketch calls them by. */
function cpuEngines(): array
{
    $driver = new PhpFramebufferDriver();

    return ['dirty' => new DirtyEngine($driver), 'full' => new FullEngine($driver)];
}

/** A GPU engine whose frame reads back as whatever a test says. @return array{FakeGPUEngineDriver, callable} */
function gpuEngine(GPUEngine $engine = GPUEngine::METAL): array
{
    $driver = new FakeGPUEngineDriver($engine);

    return [$driver, fn (string $rgba8) => $driver->executor->pixels = $rgba8];
}

/** A 16x16 OLED on a dirty canvas. @return array{EmbeddedDisplay, FakeDisplayPanel} */
function oled(): array
{
    $panel = new FakeDisplayPanel(16, 16, oledSpec());

    return [new EmbeddedDisplay('oled', $panel, panelCanvas(16, 16, oledSpec())), $panel];
}

/** A shown 64x48 Metal stage. @return array{FakeStagedWindow, FakeExecutor} */
function metalStage(int $width = 64, int $height = 48): array
{
    $executor = new FakeExecutor();

    return [(new FakeStagedWindow('main', GPUEngine::METAL, $executor, $width, $height))->setPool(bareDock())->show(), $executor];
}

it('rasterises a panel with a different CPU engine, and the panel is none the wiser', function () {
    [$display, $panel] = oled();
    $canvas = FakeEngineCanvas::of($display)->withEngines(cpuEngines());

    $canvas->engine('full')->draw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 2.0, 2.0, Color::hex('#fff')))->present();
    $display->renderFrame();

    expect($display->engine())->toBe(CPUEngine::FULL)
        ->and($display->drawableSize())->toBe([16, 16])
        ->and($display->hostFormat()->equals(oledSpec()))->toBeTrue()
        ->and($panel->transmits)->toBe([[0, 0, [3, 3, ...array_fill(0, 30, 0)], 16, 16]]);
});

it('rasterises a panel with a GPU engine that never opens a window', function () {
    [$engine, $readback] = gpuEngine();
    [$display, $panel] = oled();
    $canvas = FakeEngineCanvas::of($display)->withEngines(gpu: ['metal' => $engine]);

    $canvas->engine('metal');
    $readback(str_repeat("\xff\xff\xff\xff", 16 * 16));
    $canvas->draw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 16.0, 16.0, Color::hex('#fff')))->present();
    $display->renderFrame();

    // Attached to nothing — no native view, no lent layer — at the panel's size.
    expect($engine->hosts[0]->native_view)->toBe(0)
        ->and($engine->hosts[0]->layer)->toBe(0)
        ->and([$engine->hosts[0]->width, $engine->hosts[0]->height])->toBe([16, 16])
        ->and($display->canvas())->toBeInstanceOf(GPUCanvas::class)
        ->and($display->canvas()->gpuEngine())->toBe(GPUEngine::METAL)
        // What the GPU drew, read back and packed into the bytes this panel takes.
        ->and($panel->transmits)->toBe([[0, 0, array_fill(0, 32, 255), 16, 16]]);
});

it('gives a headless engine back when the canvas closes', function () {
    [$engine] = gpuEngine();
    [$display] = oled();
    $canvas = FakeEngineCanvas::of($display)->withEngines(gpu: ['metal' => $engine]);

    $canvas->engine('metal');
    $executor = $engine->executor;
    $canvas->close();

    expect($executor->released)->toBeTrue();
});

it('rasterises a CPU stage with a different engine and the window presents it unchanged', function () {
    $stage = (new FakeCPUStagedWindow('emu', panelCanvas(16, 16, oledSpec()), 64, 64, 1.0, StageFit::INTEGER_SCALE))->show();
    $canvas = FakeEngineCanvas::of($stage)->withEngines(cpuEngines());

    $canvas->engine('full')->draw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 16.0, 16.0, Color::hex('#fff')))->present();
    $stage->renderFrame();

    expect($stage->engine())->toBe(CPUEngine::FULL)
        ->and($stage->canvasSize())->toBe([16, 16])
        ->and(end($stage->presents))->toBe(str_repeat("\xff\xff\xff\xff", 16 * 16));
});

it('refuses a renderer that is not the size the window has been presenting', function () {
    $stage = new FakeCPUStagedWindow('emu', panelCanvas(16, 16, oledSpec()), 64, 64, 1.0, StageFit::INTEGER_SCALE);

    expect(fn () => $stage->drawWith(panelCanvas(8, 8, oledSpec())))
        ->toThrow(StageException::class, 'presents a 16x16 canvas');
});

it('draws straight through when the engine asked for is the one the target already runs', function () {
    [$stage, $executor] = metalStage();
    [$engine] = gpuEngine();
    $canvas = FakeEngineCanvas::of($stage)->withEngines(gpu: ['metal' => $engine]);
    $seen = null;

    $canvas->engine('metal')->draw(function (Drawing2D $g) use (&$seen): void {
        $seen = $g;
    })->present();
    $stage->renderFrame();

    expect($seen)->toBe($stage->drawing())
        ->and($canvas->drawing())->toBe($stage->drawing())
        ->and($engine->hosts)->toBeEmpty()
        ->and($executor->calls)->not->toContain('texture');
});

it('renders a GPU stage with a CPU engine offscreen and brings the frame in as one texture', function () {
    [$stage, $executor] = metalStage();
    $canvas = FakeEngineCanvas::of($stage)->withEngines(cpuEngines());
    $seen = null;

    $canvas->engine('dirty')->draw(function (Drawing2D $g) use (&$seen): void {
        $seen = $g;
        $g->fillRect(0.0, 0.0, 4.0, 4.0, Color::hex('#fff'));
    })->present();
    $stage->renderFrame();

    $texture = $executor->draws[0]['texture'];

    // The hook inked the CPU engine, not the window; the window's whole frame
    // went on presenting one texture, the size the Canvas measures.
    expect($seen)->not->toBe($stage->drawing())
        ->and($seen)->toBe($canvas->drawing())
        ->and($canvas->size())->toBe([64, 48])
        ->and($executor->draws)->toHaveCount(1)
        ->and([$texture->width, $texture->height])->toBe([64, 48])
        ->and($executor->released_textures)->toBe([$texture->id]);
});

it('renders a GPU view with another GPU engine running headless beside it', function () {
    $view = (new FakeWindow('main'))->gpu('scene', 'metal', 0, 0, 64, 48);
    [$engine, $readback] = gpuEngine(GPUEngine::OPENGL);
    $canvas = FakeEngineCanvas::of($view)->withEngines(gpu: ['opengl' => $engine]);

    $canvas->engine('opengl');
    $readback(str_repeat("\x00\xff\x00\xff", 64 * 48));
    $canvas->draw(fn (Drawing2D $g) => $g->fillRect(0.0, 0.0, 8.0, 8.0, Color::hex('#0f0')))->present();
    $view->renderFrame();

    $executor = $view->executor();
    $texture = $executor->draws[0]['texture'];

    expect($engine->hosts[0]->native_view)->toBe(0)
        ->and([$texture->width, $texture->height])->toBe([64, 48])
        ->and($executor->released_textures)->toBe([$texture->id]);
});

it('keeps drawing every frame the target asks for once the engine is switched', function () {
    [$stage, $executor] = metalStage();
    $canvas = FakeEngineCanvas::of($stage)->withEngines(cpuEngines());
    $frames = 0;

    $canvas->engine('dirty')->draw(function () use (&$frames): void {
        $frames++;
    })->animate();

    $stage->renderFrame();
    $stage->renderFrame();

    expect($frames)->toBe(2)
        ->and($executor->draws)->toHaveCount(2);
});

it('answers which engine is drawing, which is not what the output answers', function () {
    [$stage] = metalStage();
    [$display] = oled();
    [$metal] = gpuEngine();

    $window = FakeEngineCanvas::of($stage)->withEngines(cpuEngines());
    $panel = FakeEngineCanvas::of($display)->withEngines(gpu: ['metal' => $metal]);

    expect($window->rasteriser())->toBe(GPUEngine::METAL)
        ->and($panel->rasteriser())->toBe(CPUEngine::DIRTY);

    $window->engine('dirty');
    $panel->engine('metal');

    expect($window->rasteriser())->toBe(CPUEngine::DIRTY)
        ->and($panel->rasteriser())->toBe(GPUEngine::METAL)
        // The panel holds finished pixels either way, so it still says CPU.
        ->and($display->engine())->toBe(CPUEngine::FULL)
        ->and($stage->engine())->toBe(GPUEngine::METAL);
});

it('switching back to the target own engine drops the offscreen renderer', function () {
    [$stage, $executor] = metalStage();
    [$engine] = gpuEngine();
    $canvas = FakeEngineCanvas::of($stage)->withEngines(cpuEngines(), ['metal' => $engine]);
    $seen = null;

    $canvas->engine('dirty')->engine('metal')->draw(function (Drawing2D $g) use (&$seen): void {
        $seen = $g;
    })->present();
    $stage->renderFrame();

    expect($seen)->toBe($stage->drawing())
        ->and($executor->calls)->not->toContain('texture');
});
